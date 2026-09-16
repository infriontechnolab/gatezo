<?php

namespace App\Filament\Resources\Stalls\Pages;

use App\Filament\Resources\Stalls\StallResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStall extends EditRecord
{
    protected static string $resource = StallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
