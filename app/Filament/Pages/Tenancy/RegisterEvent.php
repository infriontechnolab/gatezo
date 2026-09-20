<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Event;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;

class RegisterEvent extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Create event';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            Select::make('type')->options(Event::TYPES)->default('other')->required(),
            TextInput::make('venue')->maxLength(255),
            TextInput::make('capacity')->numeric()->minValue(1)->helperText('Leave blank for uncapped. Advisory only, never blocks the gate.'),
            DateTimePicker::make('starts_at')->seconds(false),
            DateTimePicker::make('ends_at')->seconds(false)->afterOrEqual('starts_at'),
            Toggle::make('strict_passes')->label('Strict passes: QR changes every 30 seconds')
                ->helperText('Stops forwarded screenshots. Attendees need signal at the gate to show a live pass. You can change this later in Settings.'),
        ]);
    }

    protected function handleRegistration(array $data): Event
    {
        $event = new Event($data);
        $event->created_by = auth()->id();
        $event->save();
        $event->members()->attach(auth()->id(), ['role' => 'organizer']);

        // Sensible default so the print kit isn't empty on day one.
        $event->gates()->create(['name' => 'Main Gate', 'code' => 'G1']);

        return $event;
    }
}
