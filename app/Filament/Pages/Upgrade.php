<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\SubscriptionPlan;
use App\Models\UpgradeRequest;
use App\Notifications\UpgradeRequested;
use App\Rules\PhoneNumber;
use App\Support\Phone;
use App\Support\Plan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Notification as Mail;

/**
 * The plan catalogue for organizers. They pick a plan and billing cycle and leave a number;
 * there is no payment gateway, so the request lands in Ops (and our inbox), we call back,
 * take payment by UPI or bank transfer, and record a dated period that ends by itself.
 */
class Upgrade extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $title = 'Plans';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.upgrade';

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->onFreePlan() ? 'Upgrade' : 'Your plan';
    }

    public function getViewData(): array
    {
        /** @var Event $event */
        $event = Filament::getTenant();
        $user = auth()->user();

        return [
            'user' => $user,
            'plans' => SubscriptionPlan::catalogue()->filter(fn (SubscriptionPlan $p) => $p->is_active && ($p->isFree() || $p->isForSale()))->values(),
            'current' => Plan::of($user),
            'used' => ['events' => Plan::eventsUsed($user), 'attendees' => $event->attendees()->count(), 'team' => $event->members()->wherePivot('role', 'organizer')->count()],
            'pending' => $user->pendingUpgradeRequest(),
            'paidUntil' => $user->paidUntil(),
            'lastEnded' => $user->onFreePlan() ? $user->subscriptions()->whereDate('ends_on', '<', today()->toDateString())->max('ends_on') : null,
        ];
    }

    /** Rendered once per plan card with ['plan' => slug]. */
    public function requestAction(): Action
    {
        $plan = fn (array $arguments): ?SubscriptionPlan => ($p = SubscriptionPlan::bySlug($arguments['plan'] ?? null)) && $p->is_active && $p->isForSale() ? $p : null;

        return Action::make('request')
            ->label(fn (array $arguments) => Plan::of(auth()->user())->slug === ($arguments['plan'] ?? null) ? 'Renew' : 'Choose '.($plan($arguments)?->name ?? 'plan'))
            ->visible(fn () => ! auth()->user()->pendingUpgradeRequest())
            ->modalHeading(fn (array $arguments) => 'Choose '.$plan($arguments)?->name)
            ->modalDescription('Leave your number and we call back within a working day. You pay by UPI or bank transfer, and we switch you over the same day.')
            ->modalSubmitActionLabel('Send request')
            ->modalWidth('lg')
            ->fillForm(fn (array $arguments) => [
                'billing' => $plan($arguments)?->billingOptions()[0] ?? null,
                'phone' => auth()->user()->phone,
            ])
            ->schema(fn (array $arguments) => [
                ToggleButtons::make('billing')->label('Billing')->required()->inline()
                    ->options(collect($plan($arguments)?->billingOptions() ?? [])->mapWithKeys(fn (string $b) => [$b => $plan($arguments)->priceLabel($b)])->all()),
                TextInput::make('phone')->label('Phone for our call')->tel()->required()->maxLength(25)
                    ->rule(new PhoneNumber)->placeholder('10-digit mobile'),
                Textarea::make('note')->label('Anything we should know?')->rows(3)->maxLength(500)
                    ->placeholder('Event dates, how many people you expect, best time to call…'),
            ])
            ->action(function (array $data, array $arguments) use ($plan): void {
                $chosen = $plan($arguments);
                if (! $chosen || ! in_array($data['billing'], $chosen->billingOptions(), true)) {
                    Notification::make()->title('That plan is no longer available')->body('Reload the page and pick again.')->danger()->send();

                    return;
                }
                $user = auth()->user();
                $phone = Phone::normalise($data['phone']);
                $request = UpgradeRequest::create([
                    'user_id' => $user->id,
                    'event_id' => Filament::getTenant()->id,
                    'plan' => $chosen->slug,
                    'billing' => $data['billing'],
                    'phone' => $phone,
                    'note' => $data['note'] ?: null,
                ]);
                if (! $user->phone) {
                    $user->update(['phone' => $phone]);
                }
                if ($to = config('gatezo.signup_notify')) {
                    Mail::route('mail', $to)->notify(new UpgradeRequested($request));
                }
                Notification::make()->title('Request sent')->body("We'll call you on {$phone} within a working day.")->success()->send();
            });
    }

    public function cancelRequestAction(): Action
    {
        return Action::make('cancelRequest')->label('Cancel request')->color('gray')->link()
            ->requiresConfirmation()->modalHeading('Cancel your plan request?')
            ->action(function (): void {
                auth()->user()->pendingUpgradeRequest()?->forceFill(['status' => 'dismissed', 'handled_at' => now()])->save();
                Notification::make()->title('Request cancelled')->send();
            });
    }
}
