<?php

namespace App\Filament\Widgets;

use App\Models\Checkin;
use App\Models\DutyLog;
use App\Models\Event;
use App\Models\Shift;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who's where: each volunteer's latest duty check-in, next to where the roster says
 * they should be. Volunteers with a live shift who haven't scanned in are appended
 * as "not arrived" so gaps are visible at a glance. Polled.
 */
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
                TextColumn::make('planned')->label('Rostered')
                    ->getStateUsing(function (DutyLog $d) use ($event): string {
                        $shift = $d->volunteer ? Shift::rosteredFor($event, $d->volunteer) : null;

                        return $shift ? ($shift->gate?->name ?? $shift->label ?? 'Anywhere') : '—';
                    })
                    ->color(function (DutyLog $d) use ($event): string {
                        $shift = $d->volunteer ? Shift::rosteredFor($event, $d->volunteer) : null;

                        return $shift && $shift->gate_id && $d->status === 'on' && $shift->gate_id !== $d->gate_id ? 'warning' : 'gray';
                    }),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'on' ? 'success' : 'gray')->formatStateUsing(fn (string $state) => $state === 'on' ? 'On duty' : 'Off'),
                TextColumn::make('at')->since()->label('Since'),
                TextColumn::make('last_scan')->label('Last scan')
                    ->getStateUsing(function (DutyLog $d): string {
                        $at = Checkin::where('scanned_by', $d->volunteer_id)->where('event_id', $d->event_id)->max('scanned_at');

                        return $at ? Carbon::parse($at)->diffForHumans() : '—';
                    }),
            ])
            ->description(function () use ($event): ?string {
                // Gaps: live shifts with nobody on duty. Kept as a one-line summary so the table stays a table.
                $missing = $event->shifts()->with('gate')->get()
                    ->filter(fn (Shift $s) => in_array($s->status(), ['late', 'unlinked'], true) && $s->starts_at && $s->starts_at->isPast())
                    ->map(fn (Shift $s) => $s->volunteer_name.' ('.($s->gate?->name ?? 'anywhere').')');

                $parts = [];
                if ($missing->isNotEmpty()) {
                    $parts[] = 'Not arrived: '.$missing->join(', ');
                }
                if ($event->require_volunteer_approval) {
                    $waiting = $event->members()->wherePivot('role', 'volunteer')->wherePivotNull('approved_at')->wherePivotNull('kicked_at')->count();
                    if ($waiting) {
                        $parts[] = "{$waiting} waiting for approval (Volunteers page)";
                    }
                }

                return $parts ? implode(' · ', $parts) : null;
            });
    }
}
