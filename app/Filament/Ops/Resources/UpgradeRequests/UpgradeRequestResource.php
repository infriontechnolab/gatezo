<?php

namespace App\Filament\Ops\Resources\UpgradeRequests;

use App\Enums\UpgradeRequestStatus;
use App\Filament\Ops\Resources\Subscriptions\SubscriptionResource;
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

/** Organizers who chose a paid plan. We call back; "Confirm payment" records the period and closes the request. */
class UpgradeRequestResource extends Resource
{
    protected static ?string $model = UpgradeRequest::class;

    protected static ?string $navigationLabel = 'Plan requests';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    public static function getNavigationBadge(): ?string
    {
        $n = UpgradeRequest::where('status', UpgradeRequestStatus::Pending)->count();

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
                TextColumn::make('phone')->label('Call back on')->placeholder('—')->copyable()
                    ->state(fn (UpgradeRequest $r) => $r->contactPhone())
                    ->url(fn (UpgradeRequest $r) => $r->contactPhone() ? 'tel:'.$r->contactPhone() : null),
                TextColumn::make('event.name')->label('Event')->placeholder('—')
                    ->description(fn (UpgradeRequest $r) => $r->event ? number_format($r->event->attendees()->count()).' registered'.($r->event->starts_at ? ' · '.$r->event->starts_at->format('j M') : '') : null),
                TextColumn::make('plan')->label('Chose')->badge()->color('primary')->placeholder('—')
                    ->formatStateUsing(fn (UpgradeRequest $r) => $r->planModel()?->name ?? ucfirst((string) $r->plan))
                    ->description(fn (UpgradeRequest $r) => $r->billing ? ($r->planModel()?->priceLabel($r->billing) ?? $r->billing->getLabel()) : null),
                TextColumn::make('note')->wrap()->placeholder('—')->limit(120),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->label('Asked')->since()->sortable(),
                TextColumn::make('handler.name')->label('By')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(UpgradeRequestStatus::class)->default(UpgradeRequestStatus::Pending),
            ])
            ->recordActions([
                Action::make('done')->label('Confirm payment')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (UpgradeRequest $r) => $r->status === UpgradeRequestStatus::Pending)
                    ->modalHeading(fn (UpgradeRequest $r) => "Payment from {$r->user->name}")
                    ->modalDescription('Once the money is in. The plan runs for these dates and switches itself off after the last day.')
                    ->modalSubmitActionLabel('Record and close request')
                    ->schema(fn (UpgradeRequest $r) => SubscriptionResource::periodFields(fn () => $r->user_id))
                    ->fillForm(fn (UpgradeRequest $r) => SubscriptionResource::defaults($r->user, $r->plan, $r->billing))
                    ->action(function (UpgradeRequest $r, array $data): void {
                        $s = $r->resolve(UpgradeRequestStatus::Done, auth()->user(), $data);
                        Notification::make()->title("{$r->user->name} is on {$s->planModel()?->name} until {$s->ends_on->format('j M Y')}")->success()->send();
                    }),
                Action::make('dismiss')->label('Dismiss')->icon(Heroicon::OutlinedXMark)->color('gray')
                    ->visible(fn (UpgradeRequest $r) => $r->status === UpgradeRequestStatus::Pending)
                    ->requiresConfirmation()
                    ->action(function (UpgradeRequest $r): void {
                        $r->resolve(UpgradeRequestStatus::Dismissed, auth()->user());
                        Notification::make()->title('Dismissed')->send();
                    }),
            ])
            ->emptyStateHeading('No plan requests')
            ->emptyStateDescription('When an organizer chooses a paid plan, it lands here for a call back.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUpgradeRequests::route('/'),
        ];
    }
}
