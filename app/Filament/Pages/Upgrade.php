<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\UpgradeRequest;
use App\Notifications\UpgradeRequested;
use App\Support\Plan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Notification as Mail;

/**
 * Free → Pro, without a payment gateway yet: shows the two plans side by side, records an
 * UpgradeRequest so the lead is not lost, then opens WhatsApp so the conversation happens
 * where it happens anyway. Ops resolves it by flipping the plan.
 */
class Upgrade extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Upgrade to Pro';

    protected static ?string $title = 'Upgrade to Pro';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.upgrade';

    /** In the sidebar only for free-plan owners; the page itself stays reachable by link. */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->onFreePlan() ?? false;
    }

    public function getViewData(): array
    {
        /** @var Event $event */
        $event = Filament::getTenant();
        $user = auth()->user();

        return [
            'user' => $user,
            'event' => $event,
            'plans' => config('gatezo.plans'),
            'price' => config('gatezo.pro_price'),
            'used' => ['events' => Plan::eventsUsed($user), 'attendees' => $event->attendees()->count(), 'team' => $event->members()->wherePivot('role', 'organizer')->count()],
            'pending' => $user->pendingUpgradeRequest(),
            'whatsapp' => Plan::upgradeUrl($user, $event),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('request')->label('Request Pro')->icon('heroicon-o-sparkles')
                ->visible(fn () => auth()->user()->onFreePlan() && ! auth()->user()->pendingUpgradeRequest())
                ->modalHeading('Request Pro')
                ->modalDescription('We reply on WhatsApp, usually within the hour, and switch you over. No card, no form.')
                ->modalSubmitActionLabel('Send request and open WhatsApp')
                ->schema([
                    Textarea::make('note')->label('Anything we should know?')->rows(3)->maxLength(500)
                        ->placeholder('1,500 people expected, two gates, event is on 12 Oct…'),
                ])
                ->action(function (array $data): void {
                    $request = UpgradeRequest::create([
                        'user_id' => auth()->id(),
                        'event_id' => Filament::getTenant()->id,
                        'note' => $data['note'] ?: null,
                    ]);
                    if ($to = config('gatezo.signup_notify')) {
                        Mail::route('mail', $to)->notify(new UpgradeRequested($request));
                    }
                    Notification::make()->title('Request sent')->body('Say hi on WhatsApp so we can switch you over.')->success()
                        ->actions([Action::make('whatsapp')->label('Open WhatsApp')->url(Plan::upgradeUrl(auth()->user(), Filament::getTenant()), shouldOpenInNewTab: true)])
                        ->persistent()->send();
                    $this->redirect(Plan::upgradeUrl(auth()->user(), Filament::getTenant()), navigate: false);
                }),
        ];
    }
}
