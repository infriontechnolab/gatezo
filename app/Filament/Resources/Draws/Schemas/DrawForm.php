<?php

namespace App\Filament\Resources\Draws\Schemas;

use App\Models\Draw;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DrawForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Draw')->columns(2)->components([
                TextInput::make('name')->required()->maxLength(120)->placeholder('Grand lucky draw')->columnSpanFull(),
                Radio::make('pool_source')->label('Who is in the pool')->options(Draw::POOLS)->default('inside_now')->required()->columnSpanFull(),
                CheckboxList::make('filters.ticket_types')->label('Only these ticket types')->options(['general' => 'General', 'vip' => 'VIP', 'guest' => 'Guest'])
                    ->helperText('Leave empty for all.'),
                Toggle::make('filters.feedback_given')->label('Only people who gave feedback')->inline(false),
                Toggle::make('filters.opted_in')->label('Only people who allowed contact')->inline(false),
                Toggle::make('exclude_previous_winners')->label('Exclude winners of earlier draws at this event')->default(true)->inline(false),
            ]),
            Section::make('Prizes')->description('Drawn in this order. Put the grand prize last.')->components([
                Repeater::make('prizes')->relationship()->hiddenLabel()->orderColumn('sort_order')->reorderable()->defaultItems(1)->minItems(1)
                    ->columns(3)->schema([
                        TextInput::make('name')->required()->maxLength(120)->columnSpan(2),
                        TextInput::make('quantity')->numeric()->minValue(1)->maxValue(500)->default(1)->required(),
                    ]),
            ]),
            Section::make('Rules & stage')->columns(3)->components([
                TextInput::make('claim_minutes')->label('Minutes to reach the stage')->numeric()->minValue(1)->maxValue(60)->default(5)->required(),
                TextInput::make('alternates_per_prize')->label('Backups per prize')->numeric()->minValue(0)->maxValue(5)->default(1)->required()
                    ->helperText('Drawn upfront. If the winner does not claim in time, Forfeit moves to the next backup.'),
                Select::make('presentation.style')->label('Animation')->options(['roll' => 'Rolling names', 'wheel' => 'Wheel'])->default('roll')->native(false),
                TextInput::make('presentation.reveal_seconds')->label('Reveal after (seconds)')->numeric()->minValue(2)->maxValue(30)->default(8),
                Toggle::make('presentation.show_phone_masked')->label('Show masked phone on stage')->default(true)->inline(false),
                Toggle::make('publish_results')->label('Publish results on the event page')->default(true)->inline(false),
            ]),
        ]);
    }
}
