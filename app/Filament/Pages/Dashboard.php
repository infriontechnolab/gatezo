<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ArrivalsChart;
use App\Filament\Widgets\FeedbackChart;
use App\Filament\Widgets\GateStats;
use App\Filament\Widgets\LiveStats;
use App\Filament\Widgets\OnDutyBoard;
use App\Filament\Widgets\RegistrationsChart;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;

/** Live event dashboard. Widget order + column spans are set here, not per widget. */
class Dashboard extends BaseDashboard
{
    public function getTitle(): string
    {
        return Filament::getTenant()?->name ?? 'Dashboard';
    }

    public function getSubheading(): ?string
    {
        $event = Filament::getTenant();

        return $event?->starts_at?->isFuture()
            ? 'Starts '.$event->starts_at->diffForHumans().' · '.$event->starts_at->format('D, j M · g:i A')
            : ($event?->venue ?: null);
    }

    public function getColumns(): int|array
    {
        return 12;
    }

    public function getWidgets(): array
    {
        return [
            LiveStats::class,
            ArrivalsChart::class,
            GateStats::class,
            OnDutyBoard::class,
            RegistrationsChart::class,
            FeedbackChart::class,
        ];
    }
}
