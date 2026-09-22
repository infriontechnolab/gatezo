<?php

namespace App\Filament\Ops\Resources\Events;

use App\Filament\Ops\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use App\Support\Impersonation;
use App\Support\Plan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Every event on the platform, who owns it, how it's going. */
class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationLabel = 'Events';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('creator')->withCount(['attendees', 'checkins']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()
                    ->description(fn (Event $e) => Event::TYPES[$e->type] ?? $e->type)
                    ->url(fn (Event $e) => route('event.show', $e), shouldOpenInNewTab: true),
                TextColumn::make('creator.name')->label('Organizer')->searchable()->placeholder('— (seeded)')
                    ->description(fn (Event $e) => $e->creator ? Plan::label($e->creator->plan).' plan' : null),
                TextColumn::make('starts_at')->label('When')->dateTime('D, j M · g:i A')->sortable()->placeholder('no date'),
                TextColumn::make('attendees_count')->label('Registered')->sortable()
                    ->description(fn (Event $e) => ($limit = Plan::attendeeLimit($e)) ? "of {$limit}" : null),
                TextColumn::make('checkins_count')->label('Scans')->sortable(),
                TextColumn::make('created_at')->label('Created')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(Event::TYPES),
                Filter::make('upcoming')->label('Upcoming')->query(fn (Builder $q) => $q->where('starts_at', '>=', now())),
            ])
            ->recordActions([
                Action::make('open')->label('Open as organizer')->icon('heroicon-o-arrow-right-end-on-rectangle')->color('gray')
                    ->visible(fn (Event $e) => $e->creator !== null)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Event $e) => "Logs you in as {$e->creator->name} and opens this event's dashboard.")
                    ->action(fn (Event $e) => Impersonation::start($e->creator, $e)),
            ])
            ->emptyStateHeading('No events yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
        ];
    }
}
