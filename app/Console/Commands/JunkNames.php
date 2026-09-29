<?php

namespace App\Console\Commands;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Models\Attendee;
use App\Models\Event;
use App\Rules\PersonName;
use Illuminate\Console\Command;

/**
 * List attendees whose name fails the name rule ("%%%$$$$", "123", "A"), saved before the
 * rule existed. Read-only: review each one and fix or delete it from the edit link.
 *
 *   php artisan gatezo:junk-names
 *   php artisan gatezo:junk-names --event=sharad-utsav-x1y2
 */
class JunkNames extends Command
{
    protected $signature = 'gatezo:junk-names {--event= : Only this event (its slug)}';

    protected $description = 'List attendees whose names are not real names, with a link to fix each';

    public function handle(): int
    {
        $query = Attendee::query()->with(['event', 'pass' => fn ($q) => $q->withCount('checkins')])->orderBy('id');

        if ($slug = $this->option('event')) {
            $event = Event::where('slug', $slug)->first();
            if (! $event) {
                $this->error("No event with the slug \"{$slug}\".");

                return self::FAILURE;
            }
            $query->where('event_id', $event->id);
        }

        $rows = [];
        foreach ($query->lazy(500) as $a) {
            if (PersonName::passes($a->name)) {
                continue;
            }
            $rows[] = [
                $a->id,
                $a->event?->name ?? '—',
                json_encode($a->name, JSON_UNESCAPED_UNICODE), // quoted, so blanks and spaces show
                $a->phone ?: '—',
                $a->source ?: '—',
                $a->created_at?->format('j M Y') ?? '—',
                $a->pass?->checkins_count ? 'yes' : 'no',
                $a->event ? AttendeeResource::getUrl('edit', ['record' => $a], panel: 'admin', tenant: $a->event) : '—',
            ];
        }

        if ($rows === []) {
            $this->info('No junk names found.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Event', 'Name', 'Phone', 'Source', 'Registered', 'Scanned in', 'Edit'], $rows);
        $this->line(count($rows).' '.str('attendee')->plural(count($rows)).' to review. "Scanned in: yes" means they came to the gate, so fix the name rather than delete.');

        return self::SUCCESS;
    }
}
