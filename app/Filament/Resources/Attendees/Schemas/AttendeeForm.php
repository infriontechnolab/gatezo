<?php

namespace App\Filament\Resources\Attendees\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AttendeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120)->placeholder('Aarti Shah'),
            TextInput::make('phone')->tel()->maxLength(20)->placeholder('10-digit mobile'),
            TextInput::make('email')->email()->placeholder('aarti@example.com'),
            Select::make('ticket_type')->options(['general' => 'General', 'vip' => 'VIP', 'guest' => 'Guest'])->default('general')->placeholder('Ticket type'),
            Toggle::make('is_vip')->label('VIP'),
            Select::make('source')->options(['online' => 'Online', 'walkup' => 'Walk-up', 'import' => 'Imported'])->default('import')->placeholder('How they registered'),
        ]);
    }
}
