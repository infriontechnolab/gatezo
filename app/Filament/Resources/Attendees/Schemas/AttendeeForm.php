<?php

namespace App\Filament\Resources\Attendees\Schemas;

use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Support\Phone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AttendeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120)->rule(new PersonName)->placeholder('Aarti Shah'),
            TextInput::make('phone')->tel()->maxLength(25)->rule(new PhoneNumber)->placeholder('10-digit mobile')
                ->dehydrateStateUsing(fn (?string $state) => Phone::normalise($state)),
            TextInput::make('email')->email()->placeholder('aarti@example.com'),
            Select::make('ticket_type')->options(['general' => 'General', 'vip' => 'VIP', 'guest' => 'Guest'])->default('general')->placeholder('Ticket type'),
            Toggle::make('is_vip')->label('VIP'),
            // Where the record came from is history, not something to edit: reports count by it.
            Select::make('source')->options(['online' => 'Online', 'walkup' => 'Walk-up', 'import' => 'Imported'])->default('import')->placeholder('How they registered')
                ->disabledOn('edit'),
        ]);
    }
}
