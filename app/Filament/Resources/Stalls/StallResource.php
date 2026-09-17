<?php

namespace App\Filament\Resources\Stalls;

use App\Filament\Resources\Stalls\Pages\CreateStall;
use App\Filament\Resources\Stalls\Pages\EditStall;
use App\Filament\Resources\Stalls\Pages\ListStalls;
use App\Filament\Resources\Stalls\Schemas\StallForm;
use App\Filament\Resources\Stalls\Tables\StallsTable;
use App\Models\Stall;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StallResource extends Resource
{
    protected static ?string $model = Stall::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Stalls & feedback';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    public static function form(Schema $schema): Schema
    {
        return StallForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StallsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStalls::route('/'),
            'create' => CreateStall::route('/create'),
            'edit' => EditStall::route('/{record}/edit'),
        ];
    }
}
