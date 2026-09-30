<?php

namespace App\Services;

use App\Enums\CheckinDecision;
use App\Enums\CheckinDirection;
use App\Enums\HandoutDecision;
use App\Enums\ScanKind;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Handout;
use App\Models\Pass;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records one scan synced from a volunteer's phone: a gate check-in or a goodies handout.
 * Idempotent on client_id so the phone's offline queue can be replayed freely. Nothing is
 * ever rejected: cross-gate duplicates and goodies warnings are recorded and flagged.
 */
class ScanRecorder
{
    /**
     * @param  array{client_id: string, token: string, gate_id?: ?int, kind?: ?string, direction?: ?string, scanned_at: string, decision?: ?string}  $scan
     * @return array{client_id: string, status: string}
     */
    public function record(Event $event, User $volunteer, array $scan): array
    {
        return DB::transaction(fn (): array => [
            'client_id' => $scan['client_id'],
            'status' => $this->status($event, $volunteer, $scan),
        ]);
    }

    /** The result code the phone shows for this scan. */
    private function status(Event $event, User $volunteer, array $scan): string
    {
        $goodies = ScanKind::tryFrom($scan['kind'] ?? '') === ScanKind::Goodies;
        if (($goodies ? Handout::class : Checkin::class)::where('client_id', $scan['client_id'])->exists()) {
            return 'already_synced';
        }
        if (! PassToken::verify($scan['token'], $event, strtotime($scan['scanned_at']))) {
            return PassToken::failure($scan['token'], $event);
        }

        $pass = $event->passes()->where('code', PassToken::parse($scan['token'])['code'])->first();
        if (! $pass || $pass->revoked) {
            return $pass ? 'revoked' : 'unknown_pass';
        }

        return $goodies
            ? $this->recordHandout($event, $volunteer, $pass, $scan)
            : $this->recordCheckin($event, $volunteer, $pass, $scan);
    }

    private function recordCheckin(Event $event, User $volunteer, Pass $pass, array $scan): string
    {
        // Duplicate = this scan does not move the person. Entry while already inside
        // (or any second entry when re-entry is off), exit while already outside.
        // A forwarded screenshot shows up here: same pass, second "in", never an "out".
        $last = $pass->checkins()->where('direction', '!=', CheckinDirection::Denied)->orderByDesc('scanned_at')->orderByDesc('id')->first();
        $direction = CheckinDirection::from($scan['direction']);
        $duplicate = match ($direction) {
            CheckinDirection::In => $last?->direction === CheckinDirection::In || (! $event->allow_reentry && $last !== null),
            default => $last === null || $last->direction === CheckinDirection::Out,
        };
        $decision = CheckinDecision::tryFrom($scan['decision'] ?? '');
        if ($decision !== null && ! $duplicate) {
            $decision = null; // the phone thought it was a duplicate, the server knows better
        }

        $pass->checkins()->create([
            'event_id' => $event->id,
            'gate_id' => $scan['gate_id'] ?? null,
            'direction' => $decision === CheckinDecision::TurnedAway ? CheckinDirection::Denied : $direction,
            'scanned_by' => $volunteer->id,
            'scanned_at' => $scan['scanned_at'],
            'client_id' => $scan['client_id'],
            'duplicate_flag' => $duplicate,
            'decision' => $decision,
        ]);

        return match (true) {
            $decision === CheckinDecision::TurnedAway => CheckinDecision::TurnedAway->value,
            $duplicate => 'duplicate',
            default => 'ok',
        };
    }

    /**
     * Like the gate, the goodies counter is never blocked: the server works out whether this
     * pass should have got one, and a refusal is recorded but hands nothing out.
     */
    private function recordHandout(Event $event, User $volunteer, Pass $pass, array $scan): string
    {
        $flag = $event->goodiesFlag($pass);
        $decision = HandoutDecision::tryFrom($scan['decision'] ?? '');
        if ($decision === HandoutDecision::GaveAnyway && $flag === null) {
            $decision = null; // the phone warned, the server knows better
        }

        $pass->handouts()->create([
            'event_id' => $event->id,
            'gate_id' => $scan['gate_id'] ?? null,
            'given_by' => $volunteer->id,
            'scanned_at' => $scan['scanned_at'],
            'client_id' => $scan['client_id'],
            'flag' => $flag,
            'decision' => $decision,
        ]);

        return $decision === HandoutDecision::Refused ? HandoutDecision::Refused->value : ($flag?->value ?? 'ok');
    }
}
