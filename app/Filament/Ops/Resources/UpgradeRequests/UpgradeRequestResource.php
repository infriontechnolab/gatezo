<?php

namespace App\Filament\Ops\Resources\UpgradeRequests;

use App\Filament\Ops\Resources\UpgradeRequests\Pages\ListUpgradeRequests;
use App\Models\UpgradeRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Organizers who asked for Pro. "Mark Pro" flips their plan and closes the request. */
class UpgradeRequestResource extends Resource
{
    protected static ?string $model = UpgradeRequest::class;

    protected static ?string $navigationLabel = 'Upgrade requests';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    public static function getNavigationBadge(): ?string
    {
        $n = UpgradeRequest::where('status', 'pending')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'event', 'handler']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Organizer')->searchable()
                    ->description(fn (UpgradeRequest $r) => $r->user->email),
                TextColumn::make('user.phone')->label('Phone')->placeholder('—')
                    ->url(fn (UpgradeRequest $r) => $r->user->phone ? 'https://wa.me/'.(strlen($r->user->phone) === 10 ? '91' : '').$r->user->phone : null, shouldOpenInNewTab: true),
                TextColumn::make('event.name')->label('Event')->placeholder('—')
                    ->description(fn (UpgradeRequest $r) => $r->event ? number_format($r->event->attendees()->count()).' registered'.($r->event->starts_at ? ' · '.$r->event->starts_at->format('j M') : '') : null),
                TextColumn::make('note')->wrap()->placeholder('—')->limit(120),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'pending' => 'warning', 'done' => 'success', default => 'gray'
                }),
                TextColumn::make('created_at')->label('Asked')->since()->sortable(),
                TextColumn::make('handler.name')->label('By')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'done' => 'Done', 'dismissed' => 'Dismissed'])->default('pending'),
            ])
            ->recordActions([
                Action::make('done')->label('Mark Pro')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (UpgradeRequest $r) => $r->status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription(fn (UpgradeRequest $r) => "Puts {$r->user->name} on Pro and closes this request.")
                    ->action(function (UpgradeRequest $r): void {
                        $r->resolve('done', auth()->user());
                        Notification::make()->title("{$r->user->name} is on Pro")->success()->send();
                    }),
                Action::make('dismiss')->label('Dismiss')->icon('heroicon-o-x-mark')->color('gray')
                    ->visible(fn (UpgradeRequest $r) => $r->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (UpgradeRequest $r): void {
                        $r->resolve('dismissed', auth()->user());
                        Notification::make()->title('Dismissed')->send();
                    }),
            ])
            ->emptyStateHeading('No upgrade requests')
            ->emptyStateDescription('When an organizer clicks "Request Pro" it lands here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUpgradeRequests::route('/'),
        ];
    }
}
