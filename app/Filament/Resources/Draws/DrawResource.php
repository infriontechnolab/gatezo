<?php

namespace App\Filament\Resources\Draws;

use App\Filament\Resources\Draws\Pages\CreateDraw;
use App\Filament\Resources\Draws\Pages\EditDraw;
use App\Filament\Resources\Draws\Pages\ListDraws;
use App\Filament\Resources\Draws\Pages\StageDraw;
use App\Filament\Resources\Draws\Schemas\DrawForm;
use App\Filament\Resources\Draws\Tables\DrawsTable;
use App\Models\Draw;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DrawResource extends Resource
{
    protected static ?string $model = Draw::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Live';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Lucky draw';

    protected static ?int $navigationSort = 16;

    public static function form(Schema $schema): Schema
    {
        return DrawForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DrawsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDraws::route('/'),
            'create' => CreateDraw::route('/create'),
            'edit' => EditDraw::route('/{record}/edit'),
            'stage' => StageDraw::route('/{record}/stage'),
        ];
    }
}
