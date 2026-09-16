<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Live headcount for the current event. Livewire polling, no websockets. */
class LiveStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '3s';

    protected function getStats(): array
    {
        /** @var Event $event */
        $event = Filament::getTenant();

        $ins = $event->checkins()->where('direction', 'in')->count();
        $outs = $event->checkins()->where('direction', 'out')->count();
        $inside = max(0, $ins - $outs);
        $registered = $event->attendees()->count();
        $dupes = $event->checkins()->where('duplicate_flag', true)->count();

        $capacityNote = $event->capacity
            ? sprintf('%d%% of %d', (int) round($inside / $event->capacity * 100), $event->capacity)
            : 'no capacity set';
        $capacityColor = match (true) {
            ! $event->capacity => 'gray',
            $inside >= $event->capacity => 'danger',
            $inside >= $event->capacity * 0.9 => 'warning',
            default => 'success',
        };

        return [
            Stat::make('Inside now', number_format($inside))->description($capacityNote)->color($capacityColor),
            Stat::make('Checked in', number_format($ins))->description(number_format($registered).' registered'),
            Stat::make('Duplicate scans', number_format($dupes))->description('flagged at sync, not blocked')->color($dupes ? 'warning' : 'gray'),
            Stat::make('Volunteers on duty', number_format($event->dutyLogs()->where('status', 'on')->distinct('volunteer_id')->count('volunteer_id'))),
        ];
    }
}
