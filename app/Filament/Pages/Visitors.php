<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\Visitor;
use App\Services\VisitorBook;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Everyone who registered for any event the signed-in organizer created, one row per phone,
 * so the next event can be announced to people who said yes. The list belongs to the
 * organizer, not the current event: someone invited to help run an event sees only their own.
 */
class Visitors extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\UnitEnum|null $navigationGroup = 'People & gates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Visitors';

    protected static ?string $title = 'Visitors from all your events';

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.pages.visitors';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Visitor::where('user_id', auth()->id())->with('lastEvent'))
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->searchable()->fontFamily('mono'),
                TextColumn::make('events_count')->label('Events')->sortable()
                    ->badge()->color(fn (int $state) => $state > 1 ? 'success' : 'gray'),
                TextColumn::make('lastEvent.name')->label('Last event')->placeholder('—'),
                TextColumn::make('last_seen_at')->label('Last registered')->since()->sortable(),
                IconColumn::make('marketing_opt_in')->label('WhatsApp OK')->boolean()
                    ->tooltip(fn (Visitor $v) => $v->marketing_opt_in_at ? 'Answered '.$v->marketing_opt_in_at->format('j M Y') : 'Never asked'),
            ])
            ->filters([
                TernaryFilter::make('marketing_opt_in')->label('WhatsApp updates')
                    ->trueLabel('Said yes')->falseLabel('Not opted in'),
                Filter::make('returning')->label('Came to more than one event')
                    ->query(fn (Builder $q) => $q->where('events_count', '>', 1)),
                SelectFilter::make('event')->label('Came to')
                    ->options(fn () => Event::where('created_by', auth()->id())->latest('starts_at')->pluck('name', 'id'))
                    ->query(fn (Builder $q, array $data) => $q->when($data['value'] ?? null, fn (Builder $q, $eventId) => $q->whereExists(
                        DB::table('attendees')->whereColumn('attendees.phone', 'visitors.phone')->where('attendees.event_id', $eventId)
                    ))),
            ])
            ->recordActions([
                Action::make('whatsapp')->label('WhatsApp')->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)->color('success')
                    ->visible(fn (Visitor $v) => $v->marketing_opt_in)
                    ->url(fn (Visitor $v) => Phone::whatsappUrl($v->phone, $this->inviteMessage(str($v->name)->before(' ')->toString())), shouldOpenInNewTab: true),
                Action::make('optOut')->label('Stop messages')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (Visitor $v) => $v->marketing_opt_in)
                    ->requiresConfirmation()
                    ->modalDescription('Use this when they ask you to stop. They leave the export until they tick the box again at a future event.')
                    ->action(function (Visitor $v, VisitorBook $book): void {
                        $book->optOut($v);
                        Notification::make()->title("{$v->name} won't be messaged")->success()->send();
                    }),
            ])
            ->emptyStateHeading('No visitors yet')
            ->emptyStateDescription('Everyone who registers for an event you created, by form, walk-up, import or by hand, appears here once, however many of your events they come to.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')->label('Invite message')->icon(Heroicon::OutlinedClipboardDocument)->color('gray')
                ->modalHeading('Invite message for this event')
                ->modalDescription('Copy it into a WhatsApp broadcast. Send it only to people who said yes.')
                ->schema([TextEntry::make('message')->hiddenLabel()->state(fn () => $this->inviteMessage())->copyable()])
                ->modalSubmitAction(false)->modalCancelActionLabel('Close'),
            Action::make('export')->label('Export opted-in numbers')->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (): StreamedResponse => $this->exportOptedIn()),
        ];
    }

    /** Announces the current event, the one the organizer has open in the panel. */
    public function inviteMessage(?string $firstName = null): string
    {
        /** @var Event $event */
        $event = Filament::getTenant();
        $when = collect([$event->starts_at?->format('D j M'), $event->venue])->filter()->implode(', ');

        return ($firstName ? "Hi {$firstName}! " : 'Hi! ')
            .$event->name.($when ? " is on {$when}." : ' is coming up.')
            .' Register free and get your entry pass: '.route('event.show', $event)
            ."\n\nReply STOP if you don't want these messages.";
    }

    private function exportOptedIn(): StreamedResponse
    {
        $rows = Visitor::where('user_id', auth()->id())->where('marketing_opt_in', true)->with('lastEvent')->orderBy('name')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'WhatsApp number', 'Email', 'Events attended', 'Last event', 'Agreed on']);
            foreach ($rows as $v) {
                fputcsv($out, [$v->name, Phone::international($v->phone), $v->email, $v->events_count, $v->lastEvent?->name, $v->marketing_opt_in_at?->toDateString()]);
            }
            fclose($out);
        }, 'visitors-whatsapp-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $visitors = Visitor::where('user_id', auth()->id());

        return [
            'total' => (clone $visitors)->count(),
            'optedIn' => (clone $visitors)->where('marketing_opt_in', true)->count(),
            'returning' => (clone $visitors)->where('events_count', '>', 1)->count(),
        ];
    }
}
