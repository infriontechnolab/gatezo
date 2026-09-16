<?php

namespace App\Filament\Resources\Gates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(80)->placeholder('Main Gate'),
            TextInput::make('code')->required()->maxLength(12)->placeholder('G1')
                ->helperText('Printed under the QR as a human-readable fallback.'),
            Toggle::make('is_entry')->label('Entry gate (attendees check in here)')->default(true)
                ->helperText('Off = a duty zone only, e.g. "Food Court" for volunteer check-in.'),
        ]);
    }
}
