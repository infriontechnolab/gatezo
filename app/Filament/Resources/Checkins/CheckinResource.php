<?php

namespace App\Filament\Resources\Checkins;

use App\Filament\Resources\Checkins\Pages\ListCheckins;
use App\Filament\Resources\Checkins\Tables\CheckinsTable;
use App\Models\Checkin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CheckinResource extends Resource
{
    protected static ?string $model = Checkin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'Scan log';

    protected static ?int $navigationSort = 15;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return CheckinsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCheckins::route('/'),
        ];
    }
}
