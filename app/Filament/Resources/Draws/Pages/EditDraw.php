<?php

namespace App\Filament\Resources\Draws\Pages;

use App\Filament\Resources\Draws\DrawResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDraw extends EditRecord
{
    protected static string $resource = DrawResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(fn () => ! $this->record->isRun())];
    }

    protected function getRedirectUrl(): string
    {
        return DrawResource::getUrl('stage', ['record' => $this->record]);
    }
}
