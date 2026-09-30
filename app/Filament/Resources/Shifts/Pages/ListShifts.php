<?php

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\Actions\AddVolunteersAction;
use App\Filament\Resources\Shifts\ShiftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShifts extends ListRecords
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AddVolunteersAction::make(),
            CreateAction::make()->label('New shift'),
        ];
    }
}
