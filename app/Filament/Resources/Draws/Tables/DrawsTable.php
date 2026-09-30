<?php

namespace App\Filament\Resources\Draws\Tables;

use App\Filament\Resources\Draws\DrawResource;
use App\Models\Draw;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DrawsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('pool_source')->label('Pool')->wrap(),
                TextColumn::make('prizes_count')->counts('prizes')->label('Prizes'),
                TextColumn::make('winners_count')->counts('winners')->label('Names drawn'),
                TextColumn::make('status')->badge(),
                TextColumn::make('run_at')->since()->placeholder('—')->label('Run'),
            ])
            ->recordActions([
                Action::make('stage')->label(fn (Draw $d) => $d->isRun() ? 'Stage' : 'Run')->icon(Heroicon::OutlinedPlay)
                    ->url(fn (Draw $d) => DrawResource::getUrl('stage', ['record' => $d])),
                EditAction::make()->visible(fn (Draw $d) => ! $d->isRun()),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No draws yet')
            ->emptyStateDescription('Create one, add prizes, then run it from the Stage page when the crowd is ready.');
    }
}
