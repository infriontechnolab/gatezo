<?php

namespace App\Services;

use App\Enums\CheckinDirection;
use App\Enums\WinnerStatus;
use App\Models\DrawWinner;
use App\Models\Event;
use App\Models\Pass;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The offline bundle a volunteer's phone caches in IndexedDB: event settings, gates, draw
 * winners on stage, and every live pass with where it stands right now.
 */
class ScannerBundle
{
    /** @return array<string, mixed> */
    public function build(Event $event): array
    {
        $passes = $event->passes()
            ->with('attendee:id,name,ticket_type,is_vip')
            ->where('revoked', false)
            ->get(['id', 'attendee_id', 'code']);
        $state = $this->passStates($event);
        $goodies = $event->goodies_enabled ? $this->goodiesStates($event) : [];

        // No secret leaves the server: each cached pass carries its own signature, so the
        // phone can verify offline by comparison but cannot forge a pass it hasn't seen.
        return [
            'event' => $event->only(['slug', 'name', 'allow_reentry', 'strict_passes', 'capacity', 'accent_hex']),
            // With capacity, for the "nearly full" banner. As fresh as the last refresh.
            'inside' => $event->capacity ? $event->insideNow() : null,
            'goodies' => $event->goodies_enabled ? [
                'name' => $event->goodiesLabel(),
                'after_checkin' => $event->goodies_after_checkin,
                'ticket_types' => $event->goodies_ticket_types ?: null,
                // Approximate once offline: each phone subtracts its own handouts until the next refresh.
                'left' => $event->goodies_stock === null ? null : max(0, $event->goodies_stock - $event->handouts()->given()->count()),
            ] : null,
            'gates' => $event->gates()->get(['id', 'name', 'code', 'is_entry', 'is_goodies']),
            // Draw winners waiting on stage: scanning their pass shows WINNER + a Claim button.
            'winners' => DrawWinner::whereHas('draw', fn ($d) => $d->where('event_id', $event->id))->where('status', WinnerStatus::Announced)
                ->with(['pass:id,code', 'prize:id,name'])->get()->mapWithKeys(fn ($w) => [$w->pass->code => ['id' => $w->id, 'prize' => $w->prize->name]]),
            'passes' => $passes->map(fn (Pass $p) => [
                'code' => $p->code,
                'sig' => PassToken::sign($p->code, $event->pass_secret),
                'name' => $p->attendee->name,
                'ticket_type' => $p->attendee->ticket_type,
                'is_vip' => $p->attendee->is_vip,
                // Where this pass stands right now, so the phone can say "already inside" offline.
                'inside' => ($state[$p->id]['direction'] ?? null) === CheckinDirection::In->value,
                'entered' => isset($state[$p->id]),
                'last_at' => $state[$p->id]['at'] ?? null,
                'last_gate' => $state[$p->id]['gate'] ?? null,
                // When (and where) this pass collected goodies, so a second counter can say so offline.
                'goodies_at' => $goodies[$p->id]['at'] ?? null,
                'goodies_gate' => $goodies[$p->id]['gate'] ?? null,
            ]),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Latest real movement (in/out, never denied) per pass.
     *
     * @return array<int, array{direction: string, at: string, gate: ?string}>
     */
    private function passStates(Event $event): array
    {
        $rows = DB::select(
            'SELECT t.pass_id, t.direction, t.scanned_at, g.name AS gate FROM (
                SELECT pass_id, direction, gate_id, scanned_at,
                       ROW_NUMBER() OVER (PARTITION BY pass_id ORDER BY scanned_at DESC, id DESC) AS rn
                FROM checkins WHERE event_id = ? AND direction <> ?
             ) t LEFT JOIN gates g ON g.id = t.gate_id WHERE t.rn = 1',
            [$event->id, CheckinDirection::Denied->value],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->pass_id] = ['direction' => $r->direction, 'at' => Carbon::parse($r->scanned_at)->toIso8601String(), 'gate' => $r->gate];
        }

        return $out;
    }

    /**
     * First handout per pass.
     *
     * @return array<int, array{at: string, gate: ?string}>
     */
    private function goodiesStates(Event $event): array
    {
        $out = [];
        $rows = $event->handouts()->given()->leftJoin('gates', 'gates.id', '=', 'handouts.gate_id')
            ->orderBy('handouts.scanned_at')->get(['handouts.pass_id', 'handouts.scanned_at', 'gates.name as gate']);
        foreach ($rows as $r) {
            $out[$r->pass_id] ??= ['at' => $r->scanned_at->toIso8601String(), 'gate' => $r->gate];
        }

        return $out;
    }
}
