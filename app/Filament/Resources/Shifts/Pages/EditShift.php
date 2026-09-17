<?php

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\ShiftResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShift extends EditRecord
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /** Name changed → re-link (or unlink) the volunteer. */
    protected function afterSave(): void
    {
        if ($this->record->wasChanged('volunteer_name')) {
            $this->record->update(['volunteer_id' => null]);
            CreateShift::linkExistingVolunteer($this->record->fresh());
        }
    }
}
