<?php

namespace App\Filament\Resources\Gates\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class GateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(80)->placeholder('Main Gate'),
            TextInput::make('code')->required()->maxLength(12)->placeholder('G1')
                // Codes are unique per event (gates_event_id_code_unique); say so before the database does.
                ->unique(modifyRuleUsing: fn (Unique $rule) => $rule->where('event_id', Filament::getTenant()->id))
                ->validationMessages(['unique' => 'This event already has a gate with this code. Pick another, e.g. G2.'])
                ->helperText('Printed under the QR as a human-readable fallback.'),
            Toggle::make('is_goodies')->label('Goodies counter (hand out goodies here)')->live()
                ->helperText('Scanning a pass here gives goodies instead of checking the person in. Switch goodies on in Event settings.'),
            Toggle::make('is_entry')->label('Entry gate (attendees check in here)')->default(true)
                ->helperText('Off = a duty zone only, e.g. "Food Court" for volunteer check-in.')
                ->hidden(fn (Get $get) => $get('is_goodies')),
        ]);
    }
}
