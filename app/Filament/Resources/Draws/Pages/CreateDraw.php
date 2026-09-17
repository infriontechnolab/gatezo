<?php

namespace App\Filament\Resources\Draws\Pages;

use App\Filament\Resources\Draws\DrawResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateDraw extends CreateRecord
{
    protected static string $resource = DrawResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['event_id'] = Filament::getTenant()->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return DrawResource::getUrl('stage', ['record' => $this->record]);
    }
}
