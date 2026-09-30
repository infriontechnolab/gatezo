<?php

namespace App\Filament\Resources\Attendees\Tables;

use App\Enums\AttendeeSource;
use App\Enums\CheckinDirection;
use App\Enums\TicketType;
use App\Models\Attendee;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
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
                'pass' => fn ($p) => $p->withCount([
                    'checkins as ins' => fn ($c) => $c->where('direction', CheckinDirection::In),
                    'handouts as goodies' => fn ($h) => $h->given(),
                ]),
            ]))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('pass.code')->label('Pass')->badge()->fontFamily('mono')->copyable()
                    ->color(fn (Attendee $a) => $a->pass?->revoked ? 'danger' : 'gray')
                    ->formatStateUsing(fn (string $state, Attendee $a) => $a->pass?->revoked ? "{$state} · revoked" : $state),
                TextColumn::make('ticket_type')->badge()
                    ->formatStateUsing(fn (string $state) => TicketType::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn (string $state) => TicketType::tryFrom($state)?->getColor() ?? 'gray'),
                IconColumn::make('checked_in')->label('In')->boolean()->falseIcon('heroicon-o-minus')->falseColor('gray')->state(fn (Attendee $a) => ($a->pass?->ins ?? 0) > 0),
                IconColumn::make('got_goodies')->label('Goodies')->boolean()->trueIcon('heroicon-o-gift')->falseIcon('heroicon-o-minus')->falseColor('gray')
                    ->state(fn (Attendee $a) => ($a->pass?->goodies ?? 0) > 0)
                    ->visible(fn () => (bool) Filament::getTenant()?->goodies_enabled),
                TextColumn::make('source')->badge()->toggleable(),
                TextColumn::make('created_at')->since()->label('Registered')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('ticket_type')->options(TicketType::class),
                SelectFilter::make('source')->options(AttendeeSource::class),
                TernaryFilter::make('checked_in')->label('Checked in')
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('pass.checkins', fn ($c) => $c->where('direction', CheckinDirection::In)),
                        false: fn (Builder $q) => $q->whereDoesntHave('pass.checkins', fn ($c) => $c->where('direction', CheckinDirection::In)),
                    ),
                TernaryFilter::make('got_goodies')->label('Goodies collected')
                    ->visible(fn () => (bool) Filament::getTenant()?->goodies_enabled)
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('pass.handouts', fn ($h) => $h->given()),
                        false: fn (Builder $q) => $q->whereDoesntHave('pass.handouts', fn ($h) => $h->given()),
                    ),
            ])
            ->recordActions([
                Action::make('pass')->label('Pass')->icon('heroicon-o-qr-code')
                    ->url(fn (Attendee $a) => $a->pass ? route('pass.show', $a->pass) : null, shouldOpenInNewTab: true)
                    ->visible(fn (Attendee $a) => $a->pass && ! $a->pass->revoked),
                Action::make('revoke')->label('Revoke')->icon('heroicon-o-no-symbol')->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('The pass stops scanning at the gate. You can restore it later.')
                    ->visible(fn (Attendee $a) => $a->pass && ! $a->pass->revoked)
                    ->action(fn (Attendee $a) => $a->pass->update(['revoked' => true])),
                Action::make('restore')->label('Restore pass')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (Attendee $a) => $a->pass?->revoked)
                    ->action(fn (Attendee $a) => $a->pass->update(['revoked' => false])),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }
}
