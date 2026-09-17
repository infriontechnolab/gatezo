<?php

namespace App\Filament\Resources\Draws\Pages;

use App\Filament\Resources\Draws\DrawResource;
use App\Models\Draw;
use App\Models\DrawWinner;
use App\Services\DrawEngine;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * The organizer's phone during the draw: Run → Announce next → Claim / Forfeit.
 * The projector shows the signed presenter link; this page drives it.
 */
class StageDraw extends Page
{
    use InteractsWithRecord;

    protected static string $resource = DrawResource::class;

    protected string $view = 'filament.resources.draws.stage';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        /** @var Draw $d */
        $d = $this->record;
        if (! $d->isRun()) {
            $n = DrawEngine::pool($d)->count();

            return "{$n} eligible right now · ".Draw::POOLS[$d->pool_source];
        }

        return count($d->pool_snapshot).' in the frozen pool · run '.$d->run_at?->format('g:i A');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('presenter')->label('Open presenter screen')->icon('heroicon-o-tv')->color('gray')
                ->url(fn () => $this->record->presenterUrl(), shouldOpenInNewTab: true),
            Action::make('run')->label('Run draw')->icon('heroicon-o-play')
                ->visible(fn () => ! $this->record->isRun())
                ->requiresConfirmation()
                ->modalHeading('Run the draw?')
                ->modalDescription(fn () => 'The pool is frozen at this moment ('.DrawEngine::pool($this->record)->count().' people) and every winner and backup is decided now. Names are revealed one at a time with "Announce next".')
                ->action(function (): void {
                    DrawEngine::run($this->record, auth()->user());
                    Notification::make()->title('Draw ready')->body('Open the presenter screen, then announce the first prize.')->success()->send();
                }),
            Action::make('announce')->label('Announce next')->icon('heroicon-o-megaphone')
                ->visible(fn () => $this->record->isRun() && $this->record->status !== 'finished' && ! $this->record->current())
                ->action(function (): void {
                    $w = DrawEngine::announceNext($this->record->fresh());
                    Notification::make()->title($w ? $w->prize->name.' → '.$w->pass->attendee->name : 'All prizes done')->success()->send();
                }),
            Action::make('claim')->label('Claimed ✓')->icon('heroicon-o-check')->color('success')
                ->visible(fn () => (bool) $this->record->current())
                ->action(function (): void {
                    DrawEngine::claim($this->record->current(), auth()->user());
                    Notification::make()->title('Prize claimed')->success()->send();
                }),
            Action::make('forfeit')->label('Forfeit → next backup')->icon('heroicon-o-arrow-right')->color('danger')
                ->visible(fn () => (bool) $this->record->current())
                ->requiresConfirmation()
                ->modalDescription('The current name loses the prize. The next backup for this slot is announced immediately.')
                ->action(function (): void {
                    DrawEngine::forfeit($this->record->current());
                    $w = DrawEngine::announceNext($this->record->fresh());
                    Notification::make()->title($w ? 'Backup announced: '.$w->pass->attendee->name : 'No backups left for this slot')->warning()->send();
                }),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var Draw $d */
        $d = $this->record->fresh(['prizes', 'winners.prize', 'winners.pass.attendee']);

        return [
            'draw' => $d,
            'current' => $d->current(),
            'slotRows' => $d->winners->groupBy(fn (DrawWinner $w) => $w->prize_id.':'.$w->slot)
                ->map(fn ($group) => $group->sortBy('rank')->values())
                ->sortBy(fn ($group) => sprintf('%05d-%05d', $group->first()->prize->sort_order, $group->first()->slot))
                ->values(),
            'publicUrl' => route('draw.results', $d->event),
        ];
    }
}
