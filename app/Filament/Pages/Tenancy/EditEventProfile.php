<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\EventType;
use App\Enums\KitStyle;
use App\Enums\TicketType;
use Filament\Forms\Components\CheckboxList;
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
                TextInput::make('name')->required()->maxLength(120)->placeholder('Sharad Utsav 2026'),
                Select::make('type')->options(EventType::class)->required()->placeholder('Pick the closest'),
                TextInput::make('venue')->maxLength(255)->placeholder('Society ground, Satellite, Ahmedabad'),
                TextInput::make('capacity')->numeric()->minValue(1)->placeholder('800'),
                DateTimePicker::make('starts_at')->seconds(false)->placeholder('Event start'),
                DateTimePicker::make('ends_at')->seconds(false)->placeholder('Event end'),
                Textarea::make('description')->columnSpanFull()->rows(3)->placeholder('Shown on the registration page: what, where, what to bring.'),
            ]),
            Section::make('Look')->columns(2)->components([
                ColorPicker::make('accent_hex')->label('Accent colour')->placeholder('#E8604C'),
                FileUpload::make('logo_url')->label('Logo')->image()->disk('public')->directory('logos')->visibility('public'),
                Select::make('kit_style')->label('Print kit style')->options(KitStyle::class)->default(KitStyle::Bold)->required()->placeholder('Choose a style')
                    ->helperText('Applies to the poster, gate signs, stall and exit cards. Classic for a black-and-white shop printer; Bold and Festival look best in colour.')->columnSpanFull(),
            ]),
            Section::make('Behaviour')->columns(2)->components([
                Toggle::make('allow_self_register')->label('Walk-up registration via poster QR'),
                Toggle::make('allow_reentry')->label('Re-entry (scan out / scan in)'),
                Toggle::make('strict_passes')->label('Strict passes: QR changes every 30 seconds')
                    ->helperText('Stops forwarded screenshots. Attendees must open their live pass at the gate, so they need signal there.'),
                Toggle::make('roster_only')->label('Only names on the Shifts roster can join the scanner'),
                Toggle::make('require_volunteer_approval')->label('New volunteers wait for your approval before scanning'),
                Toggle::make('join_by_code')->label('Volunteers can join with the 6-digit code')->default(true)
                    ->helperText('Off: only the personal links from the Shifts page open the scanner. Safer for big events where the code would get forwarded around.'),
            ]),
            Section::make('Goodies')->columns(2)
                ->description('One goodies kit per attendee, handed out at a goodies counter by scanning their pass. Add the counter under Gates.')
                ->components([
                    Toggle::make('goodies_enabled')->label('Hand out goodies')->live()->columnSpanFull(),
                    TextInput::make('goodies_name')->label('What are you giving?')->maxLength(60)->placeholder('Welcome kit')
                        ->helperText('Shown to the volunteer at the counter.')->visible(fn ($get) => $get('goodies_enabled')),
                    TextInput::make('goodies_stock')->label('How many do you have?')->numeric()->minValue(0)->placeholder('Leave empty to not count')
                        ->helperText('The counter shows how many are left.')->visible(fn ($get) => $get('goodies_enabled')),
                    Toggle::make('goodies_after_checkin')->label('Only for people who have checked in at the gate')->default(true)
                        ->visible(fn ($get) => $get('goodies_enabled')),
                    CheckboxList::make('goodies_ticket_types')->label('Ticket types that get goodies')
                        ->options(TicketType::class)->columns(3)
                        ->helperText('Tick none for everyone.')->visible(fn ($get) => $get('goodies_enabled')),
                ]),
            Section::make('Codes')->columns(2)->components([
                TextInput::make('volunteer_code')->label('Volunteer join code')->disabled()->dehydrated(false)->placeholder('Generated automatically')
                    ->helperText('Volunteers type this at /scan to open the scanner. Regenerate from the dashboard if it leaks, or turn it off above and send personal links from Shifts.'),
                TextInput::make('slug')->disabled()->dehydrated(false)->prefix(url('/e/'))->label('Public link')->placeholder('Generated automatically'),
            ]),
        ]);
    }
}
