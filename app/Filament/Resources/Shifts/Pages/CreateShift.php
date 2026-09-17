<?php

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\ShiftResource;
use App\Models\Shift;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateShift extends CreateRecord
{
    protected static string $resource = ShiftResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['event_id'] = Filament::getTenant()->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /** If someone with this name already joined, link them straight away. */
    protected function afterCreate(): void
    {
        self::linkExistingVolunteer($this->record);
    }

    public static function linkExistingVolunteer(Shift $shift): void
    {
        if ($shift->volunteer_id) {
            return;
        }
        $volunteer = Filament::getTenant()->members()->wherePivot('role', 'volunteer')->get()
            ->first(fn (User $u) => Shift::normaliseName($u->name) === Shift::normaliseName($shift->volunteer_name));
        if ($volunteer) {
            $shift->update(['volunteer_id' => $volunteer->id]);
        }
    }
}
