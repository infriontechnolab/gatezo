<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\Gate;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Per-gate scan counts, polled. */
class GateStats extends TableWidget
{
    protected int|string|array $columnSpan = 6;

    public function table(Table $table): Table
    {
        /** @var Event $event */
        $event = Filament::getTenant();

        return $table
            ->heading('Gates')
            ->poll('3s')
            ->paginated(false)
            ->query(fn () => Gate::query()
                ->where('event_id', $event->id)
                ->where('is_entry', true)
                ->withCount(['checkins as ins' => fn ($q) => $q->where('direction', 'in')])
                ->withCount(['checkins as last_10m' => fn ($q) => $q->where('direction', 'in')->where('scanned_at', '>=', now()->subMinutes(10))]))
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('code')->badge()->color('gray')->fontFamily('mono'),
                TextColumn::make('ins')->label('Entry scans'),
                TextColumn::make('last_10m')->label('Last 10 min'),
            ]);
    }
}
