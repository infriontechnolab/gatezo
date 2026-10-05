<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Visitor;
use Illuminate\Support\Facades\DB;

/**
 * An organizer's visitor list: everyone who registered for any event they created, one row
 * per phone. Rows are recomputed from attendees rather than counted up, so edits, deletes and
 * re-imports can't make them drift. Attendees are read with DB::table() because inside the
 * panel the Attendee model only sees the current event.
 */
class VisitorBook
{
    /** @var array<int, array{owner: ?int, phones: list<string>}> phones of events being deleted, keyed by event id */
    private static array $pendingEventDeletes = [];

    public static function ownerOf(int $eventId): ?int
    {
        return DB::table('events')->where('id', $eventId)->value('created_by');
    }

    public function syncAttendee(Attendee $attendee): void
    {
        $owner = self::ownerOf($attendee->event_id);
        if ($owner === null) {
            return;
        }
        $phones = array_unique(array_filter([$attendee->phone, $attendee->getOriginal('phone')]));
        foreach ($phones as $phone) {
            $this->sync($owner, $phone);
        }
    }

    public function sync(int $owner, string $phone): void
    {
        $rows = DB::table('attendees')
            ->join('events', 'events.id', '=', 'attendees.event_id')
            ->where('events.created_by', $owner)
            ->where('attendees.phone', $phone)
            ->orderBy('attendees.created_at')->orderBy('attendees.id')
            ->get(['attendees.event_id', 'attendees.name', 'attendees.email', 'attendees.created_at', 'attendees.marketing_opt_in', 'attendees.marketing_opt_in_at']);

        if ($rows->isEmpty()) {
            Visitor::where('user_id', $owner)->where('phone', $phone)->delete();

            return;
        }

        $latest = $rows->last();
        // The most recent answer wins, so an opt-out at a later event withdraws an earlier yes.
        $consent = $rows->whereNotNull('marketing_opt_in')->sortBy('marketing_opt_in_at')->last();

        Visitor::updateOrCreate(['user_id' => $owner, 'phone' => $phone], [
            'name' => $latest->name,
            'email' => $rows->whereNotNull('email')->last()?->email,
            'events_count' => $rows->pluck('event_id')->unique()->count(),
            'last_event_id' => $latest->event_id,
            'first_seen_at' => $rows->first()->created_at,
            'last_seen_at' => $latest->created_at,
            'marketing_opt_in' => (bool) $consent?->marketing_opt_in,
            'marketing_opt_in_at' => $consent?->marketing_opt_in_at,
        ]);
    }

    /**
     * How many of the same organizer's earlier events each of this event's phones registered
     * for. Phones with none are left out. "Earlier" goes by event date, so a past event imported
     * after this one still counts.
     *
     * @return array<string, int>
     */
    public function earlierEventCounts(Event $event): array
    {
        if ($event->created_by === null) {
            return [];
        }
        $startedAt = $event->starts_at ?? $event->created_at;

        return DB::table('attendees')
            ->join('events', 'events.id', '=', 'attendees.event_id')
            ->where('events.created_by', $event->created_by)
            ->where('events.id', '!=', $event->id)
            ->whereRaw('COALESCE(events.starts_at, events.created_at) < ?', [$startedAt])
            ->whereIn('attendees.phone', DB::table('attendees')->where('event_id', $event->id)->whereNotNull('phone')->select('phone'))
            ->groupBy('attendees.phone')
            ->selectRaw('attendees.phone, COUNT(DISTINCT attendees.event_id) AS n')
            ->pluck('n', 'phone')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** The visitor asked to stop (usually by replying on WhatsApp). Recorded on their registrations so it survives a re-sync. */
    public function optOut(Visitor $visitor): void
    {
        DB::table('attendees')
            ->whereIn('event_id', DB::table('events')->where('created_by', $visitor->user_id)->select('id'))
            ->where('phone', $visitor->phone)
            ->whereNotNull('marketing_opt_in')
            ->update(['marketing_opt_in' => false, 'marketing_opt_in_at' => now()]);

        $this->sync($visitor->user_id, $visitor->phone);
    }

    /** Attendees go with the event in a cascade that fires no model events; remember who was there. */
    public static function eventDeleting(Event $event): void
    {
        self::$pendingEventDeletes[$event->id] = [
            'owner' => $event->created_by,
            'phones' => DB::table('attendees')->where('event_id', $event->id)->whereNotNull('phone')->distinct()->pluck('phone')->all(),
        ];
    }

    public static function eventDeleted(Event $event): void
    {
        $pending = self::$pendingEventDeletes[$event->id] ?? null;
        unset(self::$pendingEventDeletes[$event->id]);
        if ($pending === null || $pending['owner'] === null) {
            return;
        }
        $book = app(self::class);
        foreach ($pending['phones'] as $phone) {
            $book->sync($pending['owner'], $phone);
        }
    }
}
