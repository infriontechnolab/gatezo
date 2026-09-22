<?php

namespace App\Filament\Resources\Shifts\Tables;

use App\Models\Shift;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
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
                    ->description(fn (Shift $s) => $s->volunteer ? null : ($s->invite_used_at ? 'link opened' : 'not joined yet')),
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
                Action::make('invite')->label('Send link')->icon('heroicon-o-link')->color('gray')
                    ->action(fn (Shift $s) => self::inviteNotification($s)),
                Action::make('reset_invite')->label('New link')->icon('heroicon-o-arrow-path')->color('warning')
                    ->visible(fn (Shift $s) => $s->invite_used_at !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Issue a new link?')
                    ->modalDescription('The old link stops working on every phone. Use this if it was forwarded to the wrong person or they changed phones.')
                    ->action(function (Shift $s): void {
                        $s->resetInvite();
                        self::inviteNotification($s->fresh());
                    }),
                ReplicateAction::make()->label('Copy')->icon('heroicon-o-document-duplicate')
                    ->schema([
                        Select::make('gate_id')->label('To post')->placeholder('Same post')->options(fn () => Filament::getTenant()->gates()->orderBy('code')->pluck('name', 'id'))->native(false),
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
            ->emptyStateDescription('Add who is at which post and when. Each person gets a personal scanner link; volunteers see their shift when they join.');
    }

    /** Personal scanner link for one roster entry, with a WhatsApp share (the way it actually gets sent). */
    private static function inviteNotification(Shift $shift): void
    {
        $url = $shift->inviteUrl();
        $event = Filament::getTenant();
        $post = $shift->gate?->name ?? 'anywhere';
        $when = $shift->starts_at?->format('D g:i A');
        $text = "Hi {$shift->volunteer_name}, you're volunteering at {$event->name} ({$post}".($when ? ", {$when}" : '').'). '
            ."Open this on the phone you'll scan with: {$url}";

        Notification::make()
            ->title("Link for {$shift->volunteer_name}")
            ->body('Works on the first phone that opens it; forwarded copies are dead. '.$url)
            ->persistent()
            ->actions([
                Action::make('whatsapp')->label('Send on WhatsApp')->url('https://wa.me/?text='.urlencode($text), shouldOpenInNewTab: true),
                Action::make('open')->label('Copy link')->url($url, shouldOpenInNewTab: true),
            ])
            ->info()
            ->send();
    }
}
