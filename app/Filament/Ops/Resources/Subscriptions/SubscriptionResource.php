<?php

namespace App\Filament\Ops\Resources\Subscriptions;

use App\Enums\BillingCycle;
use App\Filament\Ops\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Paid periods on a plan. Money arrives by UPI or bank transfer; whoever sees it records the
 * period here (or via "Confirm payment" on a request). The plan switches itself off after ends_on.
 */
class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationLabel = 'Subscriptions';

    protected static ?string $modelLabel = 'paid period';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    /** Badge: active periods ending within the reminder window, so someone asks about renewal. */
    public static function getNavigationBadge(): ?string
    {
        $n = self::endingSoon(Subscription::query())->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Ending in the next 14 days';
    }

    public static function endingSoon(Builder $query): Builder
    {
        return $query->active()->whereDate('ends_on', '<=', today()->addDays(14)->toDateString());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'creator']);
    }

    /**
     * The period fields, shared by this screen, "Confirm payment" on a request and the
     * organizer list. Choosing plan, billing or start date fills the end date and amount.
     * $userId resolves the organizer so an overlapping period on the same plan is refused
     * (a different plan may overlap: that's an upgrade mid-period, and the better plan wins).
     *
     * @param  Closure(callable): ?int  $userId
     */
    public static function periodFields(Closure $userId): array
    {
        $fill = function (callable $get, callable $set): void {
            $plan = SubscriptionPlan::bySlug($get('plan'));
            $billing = self::billing($get('billing'));
            if (! $plan || ! $billing || ! $get('starts_on')) {
                return;
            }
            $set('ends_on', self::periodEnd(Carbon::parse($get('starts_on')), $billing)->toDateString());
            $set('amount', $plan->price($billing));
        };

        return [
            Grid::make(2)->schema([
                Select::make('plan')->required()->live()->placeholder('Choose a plan')
                    ->options(fn () => SubscriptionPlan::catalogue()->reject(fn (SubscriptionPlan $p) => $p->isFree())->pluck('name', 'slug')->all())
                    ->afterStateUpdated($fill),
                Select::make('billing')->live()->placeholder('Custom dates')
                    ->options(BillingCycle::class)
                    ->afterStateUpdated($fill),
                DatePicker::make('starts_on')->label('From')->required()->live()->placeholder('First day')
                    ->default(fn () => today())
                    ->afterStateUpdated($fill),
                DatePicker::make('ends_on')->label('Until (inclusive)')->required()->afterOrEqual('starts_on')->placeholder('Last day')
                    ->rule(fn (callable $get, ?Model $record) => function (string $attribute, $value, Closure $fail) use ($get, $record, $userId) {
                        $user = $userId($get);
                        if (! $user || ! $get('starts_on') || ! $value) {
                            return;
                        }
                        $clash = Subscription::where('user_id', $user)->where('plan', $get('plan'))
                            ->when($record instanceof Subscription, fn ($q) => $q->where('id', '!=', $record->id)) // editing it
                            ->whereDate('starts_on', '<=', Carbon::parse($value)->toDateString())
                            ->whereDate('ends_on', '>=', Carbon::parse($get('starts_on'))->toDateString())
                            ->first();
                        if ($clash) {
                            $fail("Overlaps their {$clash->starts_on->format('j M Y')} to {$clash->ends_on->format('j M Y')} period on this plan. Change that one instead.");
                        }
                    }),
                TextInput::make('amount')->label('Amount received')->prefix('₹')->numeric()->placeholder('499')->minValue(0)->maxValue(10_000_000),
                TextInput::make('payment_ref')->label('Payment reference')->maxLength(120)->placeholder('UPI ref, UTR or invoice no.'),
            ]),
            Textarea::make('note')->rows(2)->maxLength(500)->placeholder('Anything agreed on the call'),
        ];
    }

    /** Monthly from 29 Sep runs to 28 Oct; yearly to 28 Sep next year. */
    public static function periodEnd(Carbon $start, ?BillingCycle $billing): Carbon
    {
        return $start->copy()->addMonthsNoOverflow($billing?->months() ?? 12)->subDay();
    }

    /** Form state holds the enum or, straight from the browser, its value. */
    private static function billing(BillingCycle|string|null $state): ?BillingCycle
    {
        return $state instanceof BillingCycle ? $state : BillingCycle::tryFrom((string) $state);
    }

    /** Form defaults for a new period: plan and billing as asked, starting today or the day after a same-plan period ends. */
    public static function defaults(User $user, ?string $plan, ?BillingCycle $billing): array
    {
        $plan = SubscriptionPlan::bySlug($plan) ?? SubscriptionPlan::forSale()->first();
        $billing ??= $plan?->billingOptions()[0] ?? BillingCycle::Yearly;
        $start = $plan?->slug === $user->currentPlan() ? self::nextStart($user) : today();

        return [
            'plan' => $plan?->slug,
            'billing' => $billing,
            'starts_on' => $start->toDateString(),
            'ends_on' => self::periodEnd($start, $billing)->toDateString(),
            'amount' => $plan?->price($billing),
        ];
    }

    /** A renewal starts the day after the organizer's paid time runs out; otherwise today. */
    public static function nextStart(?User $user): Carbon
    {
        $until = $user?->paidUntil();

        return $until ? $until->copy()->addDay() : today();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Organizer')->required()->searchable()->placeholder('Search by name or email')
                ->options(fn () => User::organizers()->orderBy('name')->get(['id', 'name', 'email'])
                    ->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->email})"])->all())
                ->disabledOn('edit'),
            ...self::periodFields(fn (callable $get) => $get('user_id')),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ends_on', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Organizer')->searchable()->description(fn (Subscription $s) => $s->user->email),
                TextColumn::make('plan')->badge()->color('primary')
                    ->formatStateUsing(fn (Subscription $s) => $s->planModel()?->name ?? ucfirst($s->plan))
                    ->description(fn (Subscription $s) => $s->billing?->getLabel() ?? 'Custom dates'),
                TextColumn::make('starts_on')->label('From')->date('j M Y')->sortable(),
                TextColumn::make('ends_on')->label('Until')->date('j M Y')->sortable()
                    ->description(fn (Subscription $s) => match ($s->status()) {
                        'active' => today()->diffInDays($s->ends_on).' days left',
                        'upcoming' => 'starts in '.today()->diffInDays($s->starts_on).' days',
                        default => 'ended '.$s->ends_on->diffForHumans(),
                    }),
                TextColumn::make('status')->state(fn (Subscription $s) => ucfirst($s->status()))->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Active' => 'success', 'Upcoming' => 'info', default => 'gray'
                    }),
                TextColumn::make('amount')->label('Paid')->placeholder('—')->formatStateUsing(fn ($state) => '₹'.number_format($state))
                    ->description(fn (Subscription $s) => $s->payment_ref),
                TextColumn::make('note')->wrap()->limit(80)->placeholder('—')->toggleable(),
                TextColumn::make('creator.name')->label('Recorded by')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('plan')->options(fn () => SubscriptionPlan::catalogue()->reject(fn (SubscriptionPlan $p) => $p->isFree())->pluck('name', 'slug')->all()),
                SelectFilter::make('status')->options(['active' => 'Active', 'ending' => 'Ending in 14 days', 'upcoming' => 'Upcoming', 'ended' => 'Ended'])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        'active' => $q->active(),
                        'ending' => self::endingSoon($q),
                        'upcoming' => $q->whereDate('starts_on', '>', today()->toDateString()),
                        'ended' => $q->whereDate('ends_on', '<', today()->toDateString()),
                        default => $q,
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('Change dates'),
                Action::make('end')->label('End today')->icon('heroicon-o-stop-circle')->color('danger')
                    ->visible(fn (Subscription $s) => $s->isActive() && ! $s->ends_on->isToday())
                    ->requiresConfirmation()
                    ->modalDescription(fn (Subscription $s) => "{$s->user->name} keeps {$s->planModel()?->name} until the end of today, then goes back to the Free caps.")
                    ->action(function (Subscription $s): void {
                        $s->update(['ends_on' => today()]);
                        Notification::make()->title('Ends today')->success()->send();
                    }),
                DeleteAction::make()->label('Delete')
                    ->modalDescription('For a period recorded by mistake. To stop a plan early, use "End today" so the history stays.'),
            ])
            ->emptyStateHeading('No paid periods yet')
            ->emptyStateDescription('Record one after payment arrives, or use "Confirm payment" on a plan request.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptions::route('/'),
        ];
    }
}
