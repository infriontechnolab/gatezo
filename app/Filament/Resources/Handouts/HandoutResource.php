<?php

namespace App\Filament\Resources\Handouts;

use App\Filament\Resources\Handouts\Pages\ListHandouts;
use App\Filament\Resources\Handouts\Tables\HandoutsTable;
use App\Models\Handout;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HandoutResource extends Resource
{
    protected static ?string $model = Handout::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Live';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Goodies log';

    protected static ?string $modelLabel = 'goodies scan';

    protected static ?int $navigationSort = 16;

    /** Only in the menu for events that hand out goodies. */
    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Filament::getTenant()?->goodies_enabled;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return HandoutsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHandouts::route('/'),
        ];
    }
}
