<?php

namespace App\Filament\Resources\Shifts\Tables;

use App\Models\Shift;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShiftsTable
{
    public const STATUS = [
        'upcoming' => ['Upcoming', 'gray'],
        'starting' => ['Starting', 'gray'],
        'unlinked' => ['Not joined yet', 'gray'],
        'on_duty' => ['On duty', 'success'],
        'elsewhere' => ['At another post', 'warning'],
        'late' => ['Late', 'warning'],
        'missed' => ['Missed', 'danger'],
        'done' => ['Done', 'gray'],
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['gate', 'volunteer']))
            ->defaultSort('starts_at')
            ->poll('30s')
            ->groups([
                Group::make('gate.name')->label('Post')->titlePrefixedWithLabel(false)->getTitleFromRecordUsing(fn (Shift $s) => $s->gate?->name ?? 'Anywhere'),
                Group::make('volunteer_name')->label('Volunteer')->titlePrefixedWithLabel(false),
            ])
            ->defaultGroup('gate.name')
            ->columns([
                TextColumn::make('volunteer_name')->label('Volunteer')->searchable()->sortable()
                    ->description(fn (Shift $s) => $s->volunteer ? null : 'not joined yet'),
                TextColumn::make('gate.name')->label('Post')->placeholder('Anywhere'),
                TextColumn::make('starts_at')->label('From')->dateTime('D g:i A')->sortable(),
                TextColumn::make('ends_at')->label('To')->dateTime('g:i A'),
                TextColumn::make('label')->placeholder('—')->toggleable(),
                TextColumn::make('status')->badge()->state(fn (Shift $s) => $s->status())
                    ->formatStateUsing(fn (string $state) => self::STATUS[$state][0] ?? $state)
                    ->color(fn (string $state) => self::STATUS[$state][1] ?? 'gray'),
            ])
            ->filters([
                SelectFilter::make('gate_id')->label('Post')->relationship('gate', 'name'),
            ])
            ->recordActions([
                ReplicateAction::make()->label('Copy')->icon('heroicon-o-document-duplicate')
                    ->schema([
                        Select::make('gate_id')->label('To post')->options(fn () => Filament::getTenant()->gates()->orderBy('code')->pluck('name', 'id'))->native(false),
                    ])
                    ->beforeReplicaSaved(function (Shift $replica, array $data): void {
                        $replica->gate_id = $data['gate_id'] ?? $replica->gate_id;
                        $replica->volunteer_id = null;
                    })
                    ->successNotificationTitle('Shift copied'),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('No shifts yet')
            ->emptyStateDescription('Add who is at which post and when. Volunteers see their shift when they join the scanner.');
    }
}
