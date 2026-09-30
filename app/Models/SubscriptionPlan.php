<?php

namespace App\Models;

use App\Enums\BillingCycle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * A plan in the catalogue (Free, Starter, Pro…), edited in Ops → Plans. Self-serve sign-ups
 * start on Free; paid plans come from dated subscriptions. Caps, not clocks: an organizer
 * signs up weeks before the event, so a trial would expire before their first gate scan.
 *   max_events     Free: events ever created; paid: events created per month (see Plan)
 * null = unlimited. Events themselves are never capped: any number of attendees or organizers.
 */
#[Fillable(['slug', 'name', 'description', 'max_events', 'price_monthly', 'price_yearly', 'is_active', 'is_featured', 'sort'])]
class SubscriptionPlan extends Model
{
    public const FREE = 'free';

    /** Every plan, loaded once per request; cleared whenever a plan changes. */
    private static ?Collection $all = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::$all = null);
        static::deleted(fn () => self::$all = null);
    }

    protected function casts(): array
    {
        return [
            'max_events' => 'integer',
            'price_monthly' => 'integer', 'price_yearly' => 'integer',
            'is_active' => 'boolean', 'is_featured' => 'boolean', 'sort' => 'integer',
        ];
    }

    public static function catalogue(): Collection
    {
        return self::$all ??= static::query()->orderBy('sort')->get();
    }

    public static function flush(): void
    {
        self::$all = null;
    }

    public static function bySlug(?string $slug): ?self
    {
        return self::catalogue()->firstWhere('slug', $slug);
    }

    /** The free plan's caps apply to anyone whose plan can't be found. */
    public static function free(): self
    {
        return self::bySlug(self::FREE) ?? new self(['slug' => self::FREE, 'name' => 'Free', 'max_events' => 1]);
    }

    /** Active plans an organizer can pick on the Upgrade page. */
    public static function forSale(): Collection
    {
        return self::catalogue()->filter(fn (self $p) => $p->is_active && $p->isForSale())->values();
    }

    public function scopeForSale(Builder $query): Builder
    {
        return $query->where('is_active', true)->where(fn (Builder $q) => $q->whereNotNull('price_monthly')->orWhereNotNull('price_yearly'));
    }

    public function isFree(): bool
    {
        return $this->slug === self::FREE;
    }

    public function isForSale(): bool
    {
        return ! $this->isFree() && ($this->price_monthly !== null || $this->price_yearly !== null);
    }

    public function price(BillingCycle $billing): ?int
    {
        return match ($billing) {
            BillingCycle::Monthly => $this->price_monthly,
            BillingCycle::Yearly => $this->price_yearly,
        };
    }

    /** @return list<BillingCycle> billing cycles this plan is sold on */
    public function billingOptions(): array
    {
        return array_values(array_filter(BillingCycle::cases(), fn (BillingCycle $billing) => $this->price($billing) !== null));
    }

    /** "₹499 / month" */
    public function priceLabel(BillingCycle $billing): ?string
    {
        $price = $this->price($billing);

        return $price === null ? null : '₹'.number_format($price).' / '.$billing->unit();
    }
}
