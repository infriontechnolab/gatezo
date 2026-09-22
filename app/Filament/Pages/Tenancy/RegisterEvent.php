<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Event;
use App\Support\Plan;
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
            TextInput::make('name')->required()->maxLength(120)->placeholder('Sharad Utsav 2026'),
            Select::make('type')->options(Event::TYPES)->default('other')->required()->placeholder('Pick the closest'),
            TextInput::make('venue')->maxLength(255)->placeholder('Society ground, Satellite, Ahmedabad'),
            TextInput::make('capacity')->numeric()->minValue(1)->placeholder('800')->helperText('Leave blank for uncapped. Advisory only, never blocks the gate.'),
            DateTimePicker::make('starts_at')->seconds(false)->placeholder('Event start'),
            DateTimePicker::make('ends_at')->seconds(false)->afterOrEqual('starts_at')->placeholder('Event end'),
            Toggle::make('strict_passes')->label('Strict passes: QR changes every 30 seconds')
                ->helperText('Stops forwarded screenshots. Attendees need signal at the gate to show a live pass. You can change this later in Settings.'),
        ]);
    }

    protected function handleRegistration(array $data): Event
    {
        // canView() already hides the page past the free-plan cap; this is the belt for
        // a form left open in another tab.
        abort_unless(Plan::canCreateEvent(auth()->user()), 403, 'Your plan allows one event. Upgrade to create more.');

        $event = new Event($data);
        $event->created_by = auth()->id();
        $event->save();
        $event->members()->attach(auth()->id(), ['role' => 'organizer']);

        // Sensible default so the print kit isn't empty on day one.
        $event->gates()->create(['name' => 'Main Gate', 'code' => 'G1']);

        return $event;
    }
}
