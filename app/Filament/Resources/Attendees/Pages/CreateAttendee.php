<?php

namespace App\Filament\Resources\Attendees\Pages;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Support\Plan;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendee extends CreateRecord
{
    protected static string $resource = AttendeeResource::class;

    protected function beforeCreate(): void
    {
        $event = Filament::getTenant();
        if (Plan::isFull($event)) {
            Notification::make()->title('Registration is full')
                ->body('The free plan allows '.number_format(Plan::attendeeLimit($event)).' attendees per event.')
                ->danger()
                ->actions([Action::make('upgrade')->label('Upgrade to Pro')->url(Plan::upgradePageUrl($event))])
                ->send();
            $this->halt();
        }
    }

    protected function afterCreate(): void
    {
        $this->record->pass()->create(['event_id' => $this->record->event_id]);
    }
}
