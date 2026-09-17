<?php

namespace App\Filament\Resources\Draws\Tables;

use App\Filament\Resources\Draws\DrawResource;
use App\Models\Draw;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DrawsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('pool_source')->label('Pool')->formatStateUsing(fn (string $state) => Draw::POOLS[$state] ?? $state)->wrap(),
                TextColumn::make('prizes_count')->counts('prizes')->label('Prizes'),
                TextColumn::make('winners_count')->counts('winners')->label('Names drawn'),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'ready' => 'primary', 'finished' => 'success', default => 'gray'
                })
                    ->formatStateUsing(fn (string $state) => ['draft' => 'Not run', 'ready' => 'On stage', 'finished' => 'Finished'][$state] ?? $state),
                TextColumn::make('run_at')->since()->placeholder('—')->label('Run'),
            ])
            ->recordActions([
                Action::make('stage')->label(fn (Draw $d) => $d->isRun() ? 'Stage' : 'Run')->icon('heroicon-o-play')
                    ->url(fn (Draw $d) => DrawResource::getUrl('stage', ['record' => $d])),
                EditAction::make()->visible(fn (Draw $d) => ! $d->isRun()),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No draws yet')
            ->emptyStateDescription('Create one, add prizes, then run it from the Stage page when the crowd is ready.');
    }
}
