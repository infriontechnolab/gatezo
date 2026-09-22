<?php

namespace App\Filament\Pages;

use App\Http\Controllers\PrintController;
use App\Models\Event;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Links to the print kit, the post-event report and exports for the current event. */
class PrintCentre extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static ?string $navigationLabel = 'Print & reports';

    protected static ?string $title = 'Print & reports';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.print-centre';

    public function getViewData(): array
    {
        /** @var Event $event */
        $event = Filament::getTenant();

        return [
            'event' => $event,
            'kitUrl' => route('print.kit', $event),
            'reportUrl' => route('print.report', $event),
            'csvUrl' => route('print.attendees.csv', $event),
            'gateCount' => $event->gates()->count(),
            'stallCount' => $event->stalls()->count(),
            'registerUrl' => route('event.show', $event),
            'feedbackUrl' => route('event.feedback', $event),
            'scanUrl' => route('scan.join'),
            'boardUrl' => $event->boardUrl(),
            'drawsUrl' => route('draw.results', $event),
            'codes' => PrintController::codes($event),
            'zipUrl' => route('print.qr.zip', $event),
        ];
    }
}
