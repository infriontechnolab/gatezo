<?php

namespace App\Filament\Resources\Checkins\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CheckinsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['pass.attendee', 'gate', 'scanner']))
            ->columns([
                TextColumn::make('scanned_at')->dateTime('H:i:s')->sortable(),
                TextColumn::make('pass.attendee.name')->label('Attendee')->searchable(),
                TextColumn::make('pass.code')->label('Pass')->badge()->color('gray')->fontFamily('mono'),
                TextColumn::make('direction')->badge()->color(fn (string $state) => $state === 'in' ? 'success' : 'gray'),
                TextColumn::make('gate.name')->label('Gate')->placeholder('—'),
                TextColumn::make('scanner.name')->label('By')->placeholder('—'),
                IconColumn::make('duplicate_flag')->label('Dup')->boolean()->trueIcon('heroicon-o-exclamation-triangle')->falseIcon('')->trueColor('warning'),
                TextColumn::make('synced_at')->since()->label('Synced')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gate')->relationship('gate', 'name'),
                SelectFilter::make('direction')->options(['in' => 'In', 'out' => 'Out']),
                TernaryFilter::make('duplicate_flag')->label('Duplicates'),
            ])
            ->defaultSort('scanned_at', 'desc')
            ->poll('5s');
    }
}
