<?php

namespace App\Filament\Resources\Attendees\Pages;

use App\Filament\Resources\Attendees\AttendeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendee extends CreateRecord
{
    protected static string $resource = AttendeeResource::class;

    protected function afterCreate(): void
    {
        $this->record->pass()->create(['event_id' => $this->record->event_id]);
    }
}
