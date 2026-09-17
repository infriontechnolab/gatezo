<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use App\Models\VolunteerJoin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who has joined the scanner for this event, with approve / remove, plus the join log.
 * Together with "New volunteer code" on the dashboard this is the access control for
 * everyone who can scan passes.
 */
class Volunteers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\UnitEnum|null $navigationGroup = 'People & gates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Volunteers';

    protected static ?string $title = 'Volunteers';

    protected static ?int $navigationSort = 23;

    protected string $view = 'filament.pages.volunteers';

    public function table(Table $table): Table
    {
        /** @var Event $event */
        $event = Filament::getTenant();

        return $table
            ->poll('10s')
            ->query(fn (): Builder => User::query()
                ->whereHas('events', fn ($q) => $q->whereKey($event->id)->where('event_members.role', 'volunteer'))
                ->orderBy('name'))
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('status')->badge()
                    ->state(function (User $u) use ($event): string {
                        $p = $u->volunteerPivot($event);

                        return $p?->kicked_at ? 'removed' : ($p?->approved_at || ! $event->require_volunteer_approval ? 'approved' : 'waiting');
                    })
                    ->color(fn (string $state) => ['approved' => 'success', 'waiting' => 'warning', 'removed' => 'gray'][$state]),
                TextColumn::make('last_join')->label('Last joined')
                    ->getStateUsing(fn (User $u) => optional(VolunteerJoin::where('user_id', $u->id)->where('event_id', $event->id)->latest('created_at')->first())?->created_at?->diffForHumans() ?? '—'),
                TextColumn::make('device')->label('Device')
                    ->getStateUsing(fn (User $u) => VolunteerJoin::where('user_id', $u->id)->where('event_id', $event->id)->latest('created_at')->value('device') ?? '—')
                    ->fontFamily('mono')->size('xs'),
                TextColumn::make('scans')->label('Scans')
                    ->getStateUsing(fn (User $u) => $event->checkins()->where('scanned_by', $u->id)->count()),
            ])
            ->recordActions([
                Action::make('approve')->label('Approve')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (User $u) => $event->require_volunteer_approval && ($p = $u->volunteerPivot($event)) && ! $p->approved_at && ! $p->kicked_at)
                    ->action(function (User $u) use ($event): void {
                        $event->members()->wherePivot('role', 'volunteer')->updateExistingPivot($u->id, ['approved_at' => now()]);
                        Notification::make()->title("{$u->name} can scan now")->success()->send();
                    }),
                Action::make('kick')->label('Remove')->icon('heroicon-o-user-minus')->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Their scanner stops working immediately and they cannot rejoin with the current code.')
                    ->visible(fn (User $u) => ! $u->volunteerPivot($event)?->kicked_at)
                    ->action(function (User $u) use ($event): void {
                        $event->members()->wherePivot('role', 'volunteer')->updateExistingPivot($u->id, ['kicked_at' => now()]);
                        Notification::make()->title("{$u->name} removed")->warning()->send();
                    }),
                Action::make('restore')->label('Allow again')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (User $u) => (bool) $u->volunteerPivot($event)?->kicked_at)
                    ->action(fn (User $u) => $event->members()->wherePivot('role', 'volunteer')->updateExistingPivot($u->id, ['kicked_at' => null])),
            ])
            ->emptyStateHeading('Nobody has joined yet')
            ->emptyStateDescription('Volunteers appear here the moment they enter the event code at /scan.');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var Event $event */
        $event = Filament::getTenant();

        return [
            'event' => $event,
            'wrongLastHour' => VolunteerJoin::where('event_id', null)->where('result', 'wrong_code')->where('created_at', '>=', now()->subHour())->count()
                + $event->volunteerJoins()->whereIn('result', ['not_on_roster', 'kicked', 'locked_out'])->where('created_at', '>=', now()->subHour())->count(),
            'recentJoins' => $event->volunteerJoins()->latest('created_at')->limit(25)->get(),
            'waiting' => $event->require_volunteer_approval
                ? $event->members()->wherePivot('role', 'volunteer')->wherePivotNull('approved_at')->wherePivotNull('kicked_at')->count() : 0,
        ];
    }
}
