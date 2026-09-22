<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use App\Support\Plan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Co-organizers for the current event. No email needed: inviting produces a
 * set-password link the organizer forwards on WhatsApp.
 */
class Team extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $navigationLabel = 'Team';

    protected static ?string $title = 'Team';

    protected static ?int $navigationSort = 45;

    protected string $view = 'filament.pages.team';

    public function table(Table $table): Table
    {
        /** @var Event $event */
        $event = Filament::getTenant();

        return $table
            ->query(fn (): Builder => User::query()
                ->whereHas('events', fn ($q) => $q->whereKey($event->id)->where('event_members.role', 'organizer'))
                ->orderBy('name'))
            ->columns([
                TextColumn::make('name')->description(fn (User $u) => $u->id === auth()->id() ? 'you' : null),
                TextColumn::make('email'),
                TextColumn::make('created_at')->label('Added')->since(),
            ])
            ->paginated(false)
            ->recordActions([
                Action::make('reset_link')->label('Set-password link')->icon('heroicon-o-key')->color('gray')
                    ->visible(fn (User $u) => $u->id !== auth()->id())
                    ->action(fn (User $u) => $this->sendLinkNotification($u)),
                Action::make('remove')->label('Remove')->icon('heroicon-o-user-minus')->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $u) => $u->id !== auth()->id())
                    ->action(function (User $u) use ($event): void {
                        $event->members()->wherePivot('role', 'organizer')->detach($u->id);
                        Notification::make()->title("{$u->name} removed from this event")->success()->send();
                    }),
            ])
            ->emptyStateHeading('Just you so far')
            ->emptyStateDescription(fn () => Plan::canInvite($event)
                ? 'Invite a co-organizer and send them the link.'
                : 'The free plan is one organizer per event. Upgrade to Pro to invite your team.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upgrade')->label('Upgrade to add organizers')->icon('heroicon-o-sparkles')->color('gray')
                ->visible(fn () => ! Plan::canInvite(Filament::getTenant()))
                ->url(fn () => Plan::upgradeUrl(auth()->user(), Filament::getTenant()), shouldOpenInNewTab: true),
            Action::make('invite')->label('Invite organizer')->icon('heroicon-o-user-plus')
                ->visible(fn () => Plan::canInvite(Filament::getTenant()))
                ->modalDescription('They get full access to this event only. If they already have a Gatezo login, they are added straight away; otherwise you get a one-time set-password link to send them.')
                ->schema([
                    TextInput::make('name')->required()->maxLength(120)->placeholder('Hetal Modi'),
                    TextInput::make('email')->email()->required()->placeholder('hetal@example.com'),
                ])
                ->action(function (array $data): void {
                    /** @var Event $event */
                    $event = Filament::getTenant();
                    abort_unless(Plan::canInvite($event), 403, 'Your plan allows one organizer per event.');

                    $user = User::where('email', Str::lower($data['email']))->first();
                    $isNew = $user === null;
                    $user ??= User::create(['name' => $data['name'], 'email' => Str::lower($data['email']), 'password' => Str::random(40)]);

                    $event->members()->syncWithoutDetaching([$user->id => ['role' => 'organizer']]);

                    if ($isNew) {
                        $this->sendLinkNotification($user, "{$user->name} added. Send them this link to set a password:");
                    } else {
                        Notification::make()->title("{$user->name} added")->body('They already have a login; the event now appears in their switcher.')->success()->send();
                    }
                }),
        ];
    }

    private function sendLinkNotification(User $user, string $lead = 'One-time link to set a new password (valid 60 minutes):'): void
    {
        $token = Password::broker()->createToken($user);
        $url = Filament::getResetPasswordUrl($token, $user);

        Notification::make()
            ->title($user->name)
            ->body($lead.' '.$url)
            ->persistent()
            ->actions([
                Action::make('open')->label('Open link')->url($url, shouldOpenInNewTab: true),
                Action::make('whatsapp')->label('Send on WhatsApp')->url('https://wa.me/?text='.urlencode("Hi {$user->name}, you're an organizer on Gatezo. Set your password here: {$url}"), shouldOpenInNewTab: true),
            ])
            ->info()
            ->send();
    }
}
