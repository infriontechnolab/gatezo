<?php

namespace App\Filament\Resources\Handouts\Tables;

use App\Models\Handout;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HandoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['pass.attendee', 'gate', 'giver']))
            ->columns([
                TextColumn::make('scanned_at')->label('At')->dateTime('H:i:s')->sortable(),
                TextColumn::make('pass.attendee.name')->label('Attendee')->searchable(),
                TextColumn::make('pass.code')->label('Pass')->badge()->color('gray')->fontFamily('mono'),
                TextColumn::make('pass.attendee.ticket_type')->label('Ticket')->badge()->color('gray'),
                TextColumn::make('gate.name')->label('Counter')->placeholder('—'),
                TextColumn::make('giver.name')->label('By')->placeholder('—'),
                TextColumn::make('flag')->label('Warning')->badge()->placeholder('')->color('warning')
                    ->formatStateUsing(fn (?string $state) => Handout::FLAGS[$state] ?? $state),
                TextColumn::make('decision')->badge()->placeholder('')
                    ->formatStateUsing(fn (?string $state) => $state === 'refused' ? 'not given' : ($state === 'gave_anyway' ? 'given anyway' : ''))
                    ->color(fn (?string $state) => $state === 'refused' ? 'danger' : 'warning'),
                TextColumn::make('synced_at')->since()->label('Synced')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gate')->label('Counter')->relationship('gate', 'name', fn (Builder $query) => $query->where('is_goodies', true)),
                SelectFilter::make('flag')->label('Warning')->options(Handout::FLAGS),
                TernaryFilter::make('given')->label('Given')
                    ->queries(
                        true: fn (Builder $q) => $q->given(),
                        false: fn (Builder $q) => $q->where('decision', 'refused'),
                    ),
            ])
            ->defaultSort('scanned_at', 'desc')
            ->poll('5s');
    }
}
