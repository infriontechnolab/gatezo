<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ArrivalsChart;
use App\Filament\Widgets\FeedbackChart;
use App\Filament\Widgets\GateStats;
use App\Filament\Widgets\LiveStats;
use App\Filament\Widgets\OnDutyBoard;
use App\Filament\Widgets\RegistrationsChart;
use App\Models\Event;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

/** Live event dashboard. Widget order + column spans are set here, not per widget. */
class Dashboard extends BaseDashboard
{
    public function getTitle(): string
    {
        return Filament::getTenant()?->name ?? 'Dashboard';
    }

    public function getSubheading(): ?string
    {
        $event = Filament::getTenant();

        return $event?->starts_at?->isFuture()
            ? 'Starts '.$event->starts_at->diffForHumans().' · '.$event->starts_at->format('D, j M · g:i A')
            : ($event?->venue ?: null);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('duplicate')->label('Duplicate event')->icon('heroicon-o-document-duplicate')->color('gray')
                ->modalDescription('Copies gates, stalls, settings and organizers into a new event. Attendees, scans and feedback are not copied.')
                ->schema([
                    TextInput::make('name')->required()->maxLength(120)->default(fn () => Filament::getTenant()->name.' '.now()->addYear()->year),
                    DateTimePicker::make('starts_at')->seconds(false),
                    DateTimePicker::make('ends_at')->seconds(false)->afterOrEqual('starts_at'),
                ])
                ->action(function (array $data) {
                    /** @var Event $event */
                    $event = Filament::getTenant();
                    $copy = $event->duplicate(
                        $data['name'],
                        $data['starts_at'] ? Carbon::parse($data['starts_at']) : null,
                        $data['ends_at'] ? Carbon::parse($data['ends_at']) : null,
                    );
                    Notification::make()->title("Created {$copy->name}")->body('New volunteer code and pass secret. Print a fresh kit.')->success()->send();

                    return redirect(Filament::getUrl($copy));
                }),
            Action::make('rotate_code')->label('New volunteer code')->icon('heroicon-o-key')->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Issue a new volunteer code?')
                ->modalDescription('Every volunteer currently in the scanner is logged out and must rejoin with the new code. Use this if the code was shared with the wrong people.')
                ->action(function (): void {
                    $event = Filament::getTenant();
                    $event->rotateVolunteerCode();
                    Notification::make()->title('New code: '.$event->fresh()->volunteer_code)->body('Tell your volunteers. Queued scans on their phones are kept and sync after they rejoin.')->warning()->persistent()->send();
                }),
            Action::make('rotate_secret')->label('Invalidate all passes')->icon('heroicon-o-shield-exclamation')->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Invalidate every pass?')
                ->modalDescription('Every pass issued so far stops scanning. Attendees must open their pass link again to get a new QR (same link, same code). Use this if passes leaked or were mass-forwarded.')
                ->action(function (): void {
                    Filament::getTenant()->rotatePassSecret();
                    Notification::make()->title('Pass secret rotated')->body('Volunteers must be online once so their scanner downloads the new secret.')->warning()->send();
                }),
        ];
    }

    public function getColumns(): int|array
    {
        return 12;
    }

    public function getWidgets(): array
    {
        return [
            LiveStats::class,
            ArrivalsChart::class,
            GateStats::class,
            OnDutyBoard::class,
            RegistrationsChart::class,
            FeedbackChart::class,
        ];
    }
}
