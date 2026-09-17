<?php

namespace App\Filament\Widgets;

use App\Models\Checkin;
use App\Models\DutyLog;
use App\Models\Event;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Who's where: each volunteer's latest duty check-in for this event. Polled. */
class OnDutyBoard extends TableWidget
{
    protected int|string|array $columnSpan = 6;

    public function table(Table $table): Table
    {
        /** @var Event $event */
        $event = Filament::getTenant();

        return $table
            ->heading("Who's where")
            ->poll('5s')
            ->paginated(false)
            ->emptyStateHeading('No volunteers on duty yet')
            ->emptyStateDescription('They appear here when they scan a gate sign or join with the event code.')
            ->query(fn (): Builder => DutyLog::query()
                ->with(['volunteer', 'gate'])
                ->whereIn('id', DutyLog::selectRaw('MAX(id)')->where('event_id', $event->id)->groupBy('volunteer_id'))
                ->orderByDesc('at'))
            ->columns([
                TextColumn::make('volunteer.name')->label('Volunteer'),
                TextColumn::make('gate.name')->label('Post')->placeholder('—'),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'on' ? 'success' : 'gray')->formatStateUsing(fn (string $state) => $state === 'on' ? 'On duty' : 'Off'),
                TextColumn::make('at')->since()->label('Since'),
                TextColumn::make('last_scan')->label('Last scan')
                    ->getStateUsing(function (DutyLog $d): string {
                        $at = Checkin::where('scanned_by', $d->volunteer_id)->where('event_id', $d->event_id)->max('scanned_at');

                        return $at ? Carbon::parse($at)->diffForHumans() : '—';
                    }),
            ]);
    }
}
