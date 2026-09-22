<?php

namespace App\Filament\Ops\Resources\Organizers;

use App\Filament\Ops\Resources\Organizers\Pages\ListOrganizers;
use App\Models\Attendee;
use App\Models\User;
use App\Support\Impersonation;
use App\Support\Plan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;

/** Every organizer account (self-serve or hand-made), with plan and a "log in as" for support. */
class OrganizerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Organizers';

    protected static ?string $modelLabel = 'organizer';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->organizers()->withCount('createdEvents');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (User $u) => $u->email),
                TextColumn::make('phone')->placeholder('—')->copyable()
                    ->url(fn (User $u) => $u->phone ? 'https://wa.me/'.(strlen($u->phone) === 10 ? '91' : '').$u->phone : null, shouldOpenInNewTab: true),
                TextColumn::make('plan')->badge()->formatStateUsing(fn (string $state) => Plan::label($state))
                    ->color(fn (string $state) => $state === 'pro' ? 'primary' : 'gray')->sortable(),
                TextColumn::make('created_events_count')->label('Events')->sortable()
                    ->description(fn (User $u) => $u->createdEvents()->latest('starts_at')->value('name')),
                TextColumn::make('registrations')->label('Registrations')
                    ->state(fn (User $u) => number_format(Attendee::whereIn('event_id', $u->createdEvents()->select('id'))->count())),
                TextColumn::make('created_at')->label('Signed up')->since()->sortable()->description(fn (User $u) => $u->created_at->format('j M Y')),
            ])
            ->filters([
                SelectFilter::make('plan')->options(collect(Plan::names())->mapWithKeys(fn ($p) => [$p => Plan::label($p)])->all()),
                TernaryFilter::make('has_event')->label('Created an event')
                    ->queries(true: fn ($q) => $q->has('createdEvents'), false: fn ($q) => $q->doesntHave('createdEvents')),
            ])
            ->recordActions([
                Action::make('plan')->label('Plan')->icon('heroicon-o-sparkles')->color('gray')
                    ->schema([
                        Select::make('plan')->options(collect(Plan::names())->mapWithKeys(fn ($p) => [$p => Plan::label($p)])->all())
                            ->default(fn (User $u) => $u->plan)->required()->native(false),
                    ])
                    ->action(function (User $u, array $data): void {
                        $u->update(['plan' => $data['plan']]);
                        Notification::make()->title("{$u->name} is now on ".Plan::label($data['plan']))->success()->send();
                    }),
                Action::make('login_as')->label('Log in as')->icon('heroicon-o-arrow-right-end-on-rectangle')->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $u) => "Open the organizer panel as {$u->name}?")
                    ->modalDescription('You see exactly what they see. A banner in their panel brings you back here.')
                    ->action(fn (User $u) => Impersonation::start($u)),
                Action::make('reset_link')->label('Set-password link')->icon('heroicon-o-key')->color('gray')
                    ->action(function (User $u): void {
                        $token = Password::broker()->createToken($u);
                        $url = Filament::getPanel('admin')->getResetPasswordUrl($token, $u);
                        Notification::make()->title($u->name)->body('One-time link, valid 60 minutes: '.$url)->persistent()
                            ->actions([Action::make('whatsapp')->label('Send on WhatsApp')->url('https://wa.me/?text='.urlencode("Hi {$u->name}, set your Gatezo password here: {$url}"), shouldOpenInNewTab: true)])
                            ->info()->send();
                    }),
            ])
            ->emptyStateHeading('No organizers yet')
            ->emptyStateDescription('Self-serve sign-ups and gatezo:organizer accounts show up here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizers::route('/'),
        ];
    }
}
