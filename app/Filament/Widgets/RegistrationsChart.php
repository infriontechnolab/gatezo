<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/** Registrations per day for the last 14 days, with a cumulative line. Shows whether the WhatsApp forwards are working. */
class RegistrationsChart extends ChartWidget
{
    protected ?string $heading = 'Registrations, last 14 days';

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 8;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        /** @var Event $event */
        $event = Filament::getTenant();

        $days = collect(range(13, 0))->map(fn (int $i) => today()->subDays($i));
        $perDay = $event->attendees()
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n')
            ->where('created_at', '>=', $days->first())
            ->groupBy('d')->pluck('n', 'd');

        $before = $event->attendees()->where('created_at', '<', $days->first())->count();
        $daily = $days->map(fn ($d) => (int) ($perDay[$d->toDateString()] ?? 0));
        $running = $before;
        $cumulative = $daily->map(function (int $n) use (&$running) {
            return $running += $n;
        });

        return [
            'labels' => $days->map(fn ($d) => $d->format('j M'))->all(),
            'datasets' => [
                [
                    'label' => 'New',
                    'data' => $daily->all(),
                    'borderColor' => '#E8604C',
                    'backgroundColor' => 'rgba(232, 96, 76, 0.16)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 2,
                ],
                [
                    'label' => 'Total',
                    'data' => $cumulative->all(),
                    'borderColor' => '#A8A29E',
                    'borderDash' => [4, 4],
                    'tension' => 0.35,
                    'pointRadius' => 0,
                    'yAxisID' => 'y1',
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'y1' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false], 'ticks' => ['precision' => 0]],
            ],
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
        ];
    }
}
