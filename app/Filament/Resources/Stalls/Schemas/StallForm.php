<?php

namespace App\Filament\Resources\Stalls\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StallForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->components([
                TextInput::make('name')->required()->maxLength(120)->placeholder('Jalaram Khaman House'),
                TextInput::make('location')->maxLength(80)->placeholder('Row C, Stall 12'),
                Textarea::make('description')->rows(3)->columnSpanFull()->placeholder('One line attendees see when they scan the stall card.'),
                FileUpload::make('logo_url')->label('Logo')->image()->disk('public')->directory('stalls')->visibility('public'),
                Textarea::make('offers')->rows(3)->placeholder('Show this page for 10% off'),
            ]),
            Section::make('Menu / products')->components([
                Repeater::make('products')->hiddenLabel()->columns(3)->defaultItems(0)->schema([
                    TextInput::make('name')->required()->placeholder('Khaman plate'),
                    TextInput::make('price')->numeric()->prefix('₹')->placeholder('60'),
                    TextInput::make('note')->placeholder('Jain / spicy / 2 pcs'),
                ]),
            ]),
        ]);
    }
}
