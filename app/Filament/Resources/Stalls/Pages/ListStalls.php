<?php

namespace App\Filament\Resources\Stalls\Pages;

use App\Filament\Resources\Stalls\StallResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStalls extends ListRecords
{
    protected static string $resource = StallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
