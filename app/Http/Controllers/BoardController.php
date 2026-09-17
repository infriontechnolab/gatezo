<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Public "inside now" board for a tablet at the gate. Opened via a signed link from
 * Print & reports, so only the organizer can hand it out. Read-only; nothing to log into.
 */
class BoardController extends Controller
{
    public function show(Event $event): View
    {
        return view('public.board', ['event' => $event, 'stats' => self::stats($event)]);
    }

    public function json(Event $event): JsonResponse
    {
        return response()->json(self::stats($event));
    }

    /** @return array<string, mixed> */
    public static function stats(Event $event): array
    {
        $latestPerPass = Checkin::selectRaw('MAX(id)')->where('event_id', $event->id)->groupBy('pass_id');
        $inside = Checkin::whereIn('id', $latestPerPass)->where('direction', 'in')->count();
        $checkedIn = $event->checkins()->where('direction', 'in')->distinct('pass_id')->count('pass_id');
        $last15 = $event->checkins()->where('direction', 'in')->where('scanned_at', '>=', now()->subMinutes(15))->count();

        $gates = $event->gates()->where('is_entry', true)->orderBy('code')
            ->withCount(['checkins as ins' => fn ($q) => $q->where('direction', 'in')])
            ->withCount(['checkins as recent' => fn ($q) => $q->where('direction', 'in')->where('scanned_at', '>=', now()->subMinutes(10))])
            ->get(['id', 'name', 'code']);

        $pct = $event->capacity ? (int) round($inside / $event->capacity * 100) : null;

        return [
            'inside' => $inside,
            'checked_in' => $checkedIn,
            'registered' => $event->attendees()->count(),
            'capacity' => $event->capacity,
            'pct' => $pct,
            'level' => match (true) {
                $pct === null => 'none', $pct >= 100 => 'full', $pct >= 90 => 'near', default => 'ok'
            },
            'last_15' => $last15,
            'gates' => $gates->map(fn ($g) => ['name' => $g->name, 'code' => $g->code, 'ins' => (int) $g->ins, 'recent' => (int) $g->recent])->all(),
            'as_of' => now()->format('g:i:s A'),
        ];
    }
}
