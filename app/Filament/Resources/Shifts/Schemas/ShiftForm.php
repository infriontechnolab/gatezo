<?php

namespace App\Filament\Resources\Shifts\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        $event = Filament::getTenant();

        return $schema->components([
            TextInput::make('volunteer_name')->label('Volunteer')->required()->maxLength(60)->placeholder('Ravi Patel')
                ->datalist(fn () => $event->shifts()->distinct()->orderBy('volunteer_name')->pluck('volunteer_name')->all())
                ->helperText('Type the name they will use when they join with the event code. Spelling and case don\'t matter, spacing does not either.'),
            Select::make('gate_id')->label('Post')->placeholder('Anywhere')->options(fn () => $event->gates()->orderBy('code')->pluck('name', 'id'))
                ->placeholder('Anywhere / floating')->native(false),
            DateTimePicker::make('starts_at')->label('From')->seconds(false)->placeholder('Shift start')->default(fn () => $event->starts_at),
            DateTimePicker::make('ends_at')->label('To')->seconds(false)->placeholder('Shift end')->afterOrEqual('starts_at')->default(fn () => $event->ends_at),
            TextInput::make('label')->maxLength(120)->placeholder('Registration desk, parking, prasad counter…'),
        ]);
    }
}
