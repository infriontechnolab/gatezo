<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/** Check-ins per 15 minutes. Shows the peak while it's happening. */
class ArrivalsChart extends ChartWidget
{
    protected ?string $heading = 'Arrivals (per 15 min)';

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]], 'plugins' => ['legend' => ['display' => false]]];
    }

    protected function getData(): array
    {
        /** @var Event $event */
        $event = Filament::getTenant();

        $rows = $event->checkins()->where('direction', 'in')
            ->selectRaw("DATE_FORMAT(scanned_at, '%H:') as h, FLOOR(MINUTE(scanned_at)/15)*15 as m, COUNT(*) as n")
            ->groupBy('h', 'm')->orderBy('h')->orderBy('m')->get();

        return [
            'labels' => $rows->map(fn ($r) => $r->h.str_pad((string) $r->m, 2, '0', STR_PAD_LEFT))->all(),
            'datasets' => [[
                'label' => 'Check-ins',
                'data' => $rows->pluck('n')->map(fn ($n) => (int) $n)->all(),
                'backgroundColor' => '#E8604C',
                'borderRadius' => 6,
                'maxBarThickness' => 28,
            ]],
        ];
    }
}
