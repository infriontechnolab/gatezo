<?php

namespace App\Filament\Resources\Draws\Pages;

use App\Filament\Resources\Draws\DrawResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDraws extends ListRecords
{
    protected static string $resource = DrawResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
