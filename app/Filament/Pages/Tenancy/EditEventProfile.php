<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Event;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditEventProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Event settings';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Basics')->columns(2)->components([
                TextInput::make('name')->required()->maxLength(120),
                Select::make('type')->options(Event::TYPES)->required(),
                TextInput::make('venue')->maxLength(255),
                TextInput::make('capacity')->numeric()->minValue(1),
                DateTimePicker::make('starts_at')->seconds(false),
                DateTimePicker::make('ends_at')->seconds(false),
                Textarea::make('description')->columnSpanFull()->rows(3),
            ]),
            Section::make('Look')->columns(2)->components([
                ColorPicker::make('accent_hex')->label('Accent colour'),
                FileUpload::make('logo_url')->label('Logo')->image()->directory('logos')->visibility('public'),
                Select::make('kit_style')->label('Print kit style')->options(Event::KIT_STYLES)->default('bold')->required()->native(false)
                    ->helperText('Applies to the poster, gate signs, stall and exit cards. Classic for a black-and-white shop printer; Bold and Festival look best in colour.')->columnSpanFull(),
            ]),
            Section::make('Behaviour')->columns(2)->components([
                Toggle::make('allow_self_register')->label('Walk-up registration via poster QR'),
                Toggle::make('allow_reentry')->label('Re-entry (scan out / scan in)'),
                Toggle::make('strict_passes')->label('Strict passes: QR changes every 30 seconds')
                    ->helperText('Stops forwarded screenshots. Attendees must open their live pass at the gate, so they need signal there.'),
                Toggle::make('roster_only')->label('Only names on the Shifts roster can join the scanner'),
                Toggle::make('require_volunteer_approval')->label('New volunteers wait for your approval before scanning'),
            ]),
            Section::make('Codes')->columns(2)->components([
                TextInput::make('volunteer_code')->label('Volunteer join code')->disabled()->dehydrated(false)
                    ->helperText('Volunteers type this at /scan to open the scanner. Regenerate from the dashboard if it leaks.'),
                TextInput::make('slug')->disabled()->dehydrated(false)->prefix(url('/e/'))->label('Public link'),
            ]),
        ]);
    }
}
