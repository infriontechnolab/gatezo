<?php

namespace App\Filament\Resources\Attendees\Pages;

use App\Filament\Resources\Attendees\AttendeeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListAttendees extends ListRecords
{
    protected static string $resource = AttendeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->url(fn () => route('print.attendees.csv', Filament::getTenant()), shouldOpenInNewTab: true),
            CreateAction::make(),
        ];
    }
}
