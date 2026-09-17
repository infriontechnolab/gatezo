<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/** Rating distribution + average in the heading. */
class FeedbackChart extends ChartWidget
{
    protected int|string|array $columnSpan = 4;

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = '30s';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): string
    {
        /** @var Event $event */
        $event = Filament::getTenant();
        $avg = $event->feedback()->avg('rating');
        $n = $event->feedback()->count();

        return $avg ? sprintf('Feedback · %.1f ★ from %d', $avg, $n) : 'Feedback · none yet';
    }

    protected function getData(): array
    {
        /** @var Event $event */
        $event = Filament::getTenant();
        $counts = $event->feedback()->select('rating', DB::raw('count(*) as n'))->groupBy('rating')->pluck('n', 'rating');

        return [
            'labels' => ['1 ★', '2 ★', '3 ★', '4 ★', '5 ★'],
            'datasets' => [[
                'label' => 'Responses',
                'data' => collect([1, 2, 3, 4, 5])->map(fn ($r) => (int) ($counts[$r] ?? 0))->all(),
                'backgroundColor' => ['#F8CFC7', '#F4AFA3', '#EF8A78', '#E8604C', '#B8412F'], // primary 200→700, low→high
                'borderRadius' => 6,
                'maxBarThickness' => 36,
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['ticks' => ['precision' => 0]]], 'plugins' => ['legend' => ['display' => false]]];
    }
}
