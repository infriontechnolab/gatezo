<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Draw;
use App\Models\DrawWinner;
use App\Models\Pass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lucky draw, reproducible by anyone:
 *
 *   order(pass) = HMAC-SHA256(seed, pass.code)   → sort ascending
 *   walk prizes in sort_order; for each slot take the next entries:
 *   1 winner + N alternates
 *
 * The seed's hash is stored when the draw is created (before the pool exists),
 * the seed is revealed when it runs, and the pool is frozen in pool_snapshot.
 * Given snapshot + seed, this file's logic yields exactly the same winners.
 */
final class DrawEngine
{
    /** Eligible passes for this draw, in a stable (id) order before seeding. */
    public static function pool(Draw $draw): Builder
    {
        $event = $draw->event;
        $q = Pass::query()->where('event_id', $event->id)->where('revoked', false)->with('attendee');

        match ($draw->pool_source) {
            'inside_now' => $q->whereIn('id', Checkin::select('pass_id')->whereIn('id',
                Checkin::selectRaw('MAX(id)')->where('event_id', $event->id)->groupBy('pass_id')
            )->where('direction', 'in')),
            'checked_in' => $q->whereHas('checkins', fn ($c) => $c->where('direction', 'in')),
            default => null,
        };

        $f = $draw->filters ?? [];
        if (! empty($f['ticket_types'])) {
            $q->whereHas('attendee', fn ($a) => $a->whereIn('ticket_type', $f['ticket_types']));
        }
        if (! empty($f['feedback_given'])) {
            $q->whereHas('attendee.feedback');
        }
        if (! empty($f['opted_in'])) {
            $q->whereHas('attendee', fn ($a) => $a->where('share_contact', true));
        }
        if ($draw->exclude_previous_winners) {
            $q->whereDoesntHave('drawWinners', fn ($w) => $w->where('draw_id', '!=', $draw->id)
                ->whereHas('draw', fn ($d) => $d->where('event_id', $event->id))
                ->whereIn('status', ['claimed', 'announced']));
        }

        return $q->orderBy('id');
    }

    /** Freeze the pool, reveal the seed, assign every slot. Idempotent: refuses to run twice. */
    public static function run(Draw $draw, ?User $by = null): Draw
    {
        abort_if($draw->isRun(), 409, 'This draw has already been run.');
        abort_if($draw->prizes()->count() === 0, 422, 'Add at least one prize first.');

        return DB::transaction(function () use ($draw, $by) {
            $pool = self::pool($draw)->lockForUpdate()->get();
            abort_if($pool->isEmpty(), 422, 'Nobody is eligible for this pool right now.');

            $seed = $draw->seed; // hidden attribute, set at create
            $ordered = $pool->sortBy(fn (Pass $p) => hash_hmac('sha256', $p->code, $seed))->values();

            $draw->forceFill([
                'pool_snapshot' => $ordered->map(fn (Pass $p) => [
                    'code' => $p->code,
                    'name' => $p->attendee->name,
                    'phone_masked' => Draw::maskPhone($p->attendee->phone),
                ])->all(),
                'status' => 'ready',
                'run_at' => now(),
                'run_by' => $by?->id,
            ])->save();

            $i = 0;
            $n = $ordered->count();
            foreach ($draw->prizes as $prize) {
                for ($slot = 1; $slot <= $prize->quantity; $slot++) {
                    for ($rank = 1; $rank <= 1 + $draw->alternates_per_prize; $rank++) {
                        if ($i >= $n) {
                            break 3; // pool exhausted: remaining slots simply have no names
                        }
                        DrawWinner::create([
                            'draw_id' => $draw->id, 'prize_id' => $prize->id, 'pass_id' => $ordered[$i++]->id,
                            'slot' => $slot, 'rank' => $rank, 'status' => 'pending',
                        ]);
                    }
                }
            }

            return $draw->fresh();
        });
    }

    /** Put the next pending slot on stage: lowest prize order, lowest slot, rank 1 first (or the next alternate). */
    public static function announceNext(Draw $draw): ?DrawWinner
    {
        abort_if($draw->current(), 409, 'Claim or forfeit the current winner first.');

        $next = self::nextPending($draw);
        if (! $next) {
            $draw->forceFill(['status' => 'finished'])->save();

            return null;
        }
        $next->update(['status' => 'announced', 'announced_at' => now(), 'claim_deadline' => now()->addMinutes($draw->claim_minutes)]);

        return $next->fresh(['prize', 'pass.attendee']);
    }

    /** Next slot that still needs a name on stage. */
    public static function nextPending(Draw $draw): ?DrawWinner
    {
        $slotsDone = DrawWinner::where('draw_id', $draw->id)->where('status', 'claimed')->get(['prize_id', 'slot'])
            ->map(fn ($w) => $w->prize_id.':'.$w->slot);

        return DrawWinner::where('draw_id', $draw->id)->where('status', 'pending')
            ->with('prize')
            ->get()
            ->reject(fn ($w) => $slotsDone->contains($w->prize_id.':'.$w->slot))
            ->sortBy(fn ($w) => sprintf('%05d-%05d-%03d', $w->prize->sort_order, $w->slot, $w->rank))
            ->first();
    }

    public static function claim(DrawWinner $w, ?User $verifiedBy = null): DrawWinner
    {
        abort_unless($w->status === 'announced', 409, 'This name is not on stage.');
        $w->update(['status' => 'claimed', 'claimed_at' => now(), 'verified_by' => $verifiedBy?->id]);
        // Alternates for the same slot are no longer needed.
        DrawWinner::where('draw_id', $w->draw_id)->where('prize_id', $w->prize_id)->where('slot', $w->slot)
            ->where('status', 'pending')->delete();

        return $w->fresh();
    }

    public static function forfeit(DrawWinner $w): DrawWinner
    {
        abort_unless($w->status === 'announced', 409, 'This name is not on stage.');
        $w->update(['status' => 'forfeited']);

        return $w->fresh();
    }

    /** What the presenter/pass/results pages read. Public-safe: masked phone, short name. */
    public static function publicState(Draw $draw): array
    {
        $draw->loadMissing(['prizes', 'winners.prize', 'winners.pass.attendee']);
        $show = ($draw->presentation['show_phone_masked'] ?? true);
        $fmt = fn (DrawWinner $w) => [
            'id' => $w->id,
            'prize' => $w->prize->name,
            'slot' => $w->slot,
            'rank' => $w->rank,
            'name' => Draw::shortName($w->pass->attendee->name),
            'phone' => $show ? Draw::maskPhone($w->pass->attendee->phone) : null,
            'status' => $w->status,
            'deadline' => $w->claim_deadline?->toIso8601String(),
            'seconds_left' => $w->claim_deadline ? max(0, now()->diffInSeconds($w->claim_deadline, false)) : null,
        ];
        $current = $draw->current();

        return [
            'name' => $draw->name,
            'status' => $draw->status,
            'current' => $current ? $fmt($current) : null,
            'claimed' => $draw->winners->where('status', 'claimed')->sortBy('claimed_at')->map($fmt)->values()->all(),
            'prizes' => $draw->prizes->map(fn ($p) => ['name' => $p->name, 'quantity' => $p->quantity])->all(),
            'pool_size' => count($draw->pool_snapshot ?? []),
            'names' => collect($draw->pool_snapshot ?? [])->pluck('name')->map(fn ($n) => Draw::shortName($n))->shuffle()->take(60)->values()->all(),
            'seed_hash' => $draw->seed_hash,
            'seed' => $draw->status === 'finished' ? $draw->getAttribute('seed') : null,
            'presentation' => $draw->presentation,
            'as_of' => now()->toIso8601String(),
        ];
    }
}
