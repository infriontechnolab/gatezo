<?php

namespace App\Filament\Widgets;

use App\Models\Checkin;
use App\Models\Event;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Live headcount for the current event. Livewire polling, no websockets. */
class LiveStats extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '3s';

    protected function getStats(): array
    {
        /** @var Event $event */
        $event = Filament::getTenant();

        // People, not scans: a pass is "inside" if its most recent scan was an entry.
        $latestPerPass = Checkin::selectRaw('MAX(id)')->where('event_id', $event->id)->where('direction', '<>', 'denied')->groupBy('pass_id');
        $inside = Checkin::whereIn('id', $latestPerPass)->where('direction', 'in')->count();
        $ins = $event->checkins()->where('direction', 'in')->distinct('pass_id')->count('pass_id');
        $registered = $event->attendees()->count();
        $dupes = $event->checkins()->where('duplicate_flag', true)->count();
        $turned = $event->checkins()->where('decision', 'turned_away')->count();
        $letIn = $event->checkins()->where('decision', 'let_in')->count();
        $onDuty = $event->dutyLogs()->where('status', 'on')->distinct('volunteer_id')->count('volunteer_id');

        // Last 60 minutes of arrivals in 5-minute buckets, for the trend indicator.
        $spark = collect(range(11, 0))->map(function (int $i) use ($event) {
            $from = now()->subMinutes(($i + 1) * 5);

            return $event->checkins()->where('direction', 'in')->whereBetween('scanned_at', [$from, $from->copy()->addMinutes(5)])->count();
        });
        $last5 = $spark->last();
        $prev5 = $spark->slice(-2, 1)->first() ?? 0;
        $trend = $last5 > $prev5 ? 'up' : ($last5 < $prev5 ? 'down' : 'flat');

        $capacityNote = $event->capacity
            ? sprintf('%d%% of %s capacity', (int) round($inside / $event->capacity * 100), number_format($event->capacity))
            : 'no capacity set';
        $capacityColor = match (true) {
            $event->capacity && $inside >= $event->capacity => 'danger',
            $event->capacity && $inside >= $event->capacity * 0.9 => 'warning',
            default => 'gray',
        };

        // Colour rule: cards are neutral. A card only turns warning/danger when it is one.
        return [
            Stat::make('Inside now', number_format($inside))
                ->icon(Heroicon::OutlinedUserGroup)
                ->description($capacityNote)
                ->descriptionIcon($capacityColor === 'gray' ? null : Heroicon::ExclamationTriangle)
                ->color($capacityColor),

            Stat::make('Arrivals, last 5 min', number_format($last5))
                ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                ->description(match ($trend) {
                    'up' => 'Picking up vs previous 5 min', 'down' => 'Slowing vs previous 5 min', default => 'Steady'
                })
                ->descriptionIcon(match ($trend) {
                    'up' => Heroicon::ArrowTrendingUp, 'down' => Heroicon::ArrowTrendingDown, default => Heroicon::Minus
                })
                ->color('gray'),

            Stat::make('Checked in', number_format($ins))
                ->icon(Heroicon::OutlinedQrCode)
                ->description(number_format($registered).' registered · '.($registered ? round($ins / $registered * 100) : 0).'% showed up')
                ->color('gray'),

            Stat::make('Duplicates · On duty', number_format($dupes).' · '.number_format($onDuty))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->description($dupes ? number_format($turned).' turned away · '.number_format($letIn).' let in by a volunteer · '.number_format($dupes - $turned - $letIn).' flagged at sync' : 'No duplicate scans · '.$onDuty.' volunteer'.($onDuty === 1 ? '' : 's').' on duty')
                ->descriptionIcon($dupes ? Heroicon::ExclamationTriangle : null)
                ->color($dupes ? 'warning' : 'gray'),
        ];
    }
}
