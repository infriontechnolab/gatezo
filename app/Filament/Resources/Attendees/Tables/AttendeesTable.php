<?php

namespace App\Filament\Resources\Attendees\Tables;

use App\Models\Attendee;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'pass' => fn ($p) => $p->withCount(['checkins as ins' => fn ($c) => $c->where('direction', 'in')]),
            ]))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('pass.code')->label('Pass')->badge()->copyable(),
                TextColumn::make('ticket_type')->badge()->color(fn (string $state) => $state === 'vip' ? 'warning' : 'gray'),
                IconColumn::make('checked_in')->label('In')->boolean()->state(fn (Attendee $a) => ($a->pass?->ins ?? 0) > 0),
                TextColumn::make('source')->badge()->color('gray')->toggleable(),
                TextColumn::make('created_at')->since()->label('Registered')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('ticket_type')->options(['general' => 'General', 'vip' => 'VIP', 'guest' => 'Guest']),
                SelectFilter::make('source')->options(['online' => 'Online', 'walkup' => 'Walk-up', 'import' => 'Imported']),
                TernaryFilter::make('checked_in')->label('Checked in')
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('pass.checkins', fn ($c) => $c->where('direction', 'in')),
                        false: fn (Builder $q) => $q->whereDoesntHave('pass.checkins', fn ($c) => $c->where('direction', 'in')),
                    ),
            ])
            ->recordActions([
                Action::make('pass')->label('Pass')->icon('heroicon-o-qr-code')
                    ->url(fn (Attendee $a) => $a->pass ? route('pass.show', $a->pass) : null, shouldOpenInNewTab: true)
                    ->visible(fn (Attendee $a) => (bool) $a->pass),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }
}
