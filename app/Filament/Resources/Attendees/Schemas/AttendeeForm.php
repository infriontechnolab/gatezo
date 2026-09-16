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
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('phone')->tel()->maxLength(20),
            TextInput::make('email')->email(),
            Select::make('ticket_type')->options(['general' => 'General', 'vip' => 'VIP', 'guest' => 'Guest'])->default('general'),
            Toggle::make('is_vip')->label('VIP'),
            Select::make('source')->options(['online' => 'Online', 'walkup' => 'Walk-up', 'import' => 'Imported'])->default('import'),
        ]);
    }
}
