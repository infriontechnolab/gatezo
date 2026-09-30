<?php

namespace App\Models;

use App\Enums\BillingCycle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One paid period on a plan, recorded by Ops after the money arrives. Both dates are inclusive. */
#[Fillable(['user_id', 'plan', 'billing', 'starts_on', 'ends_on', 'amount', 'payment_ref', 'note', 'upgrade_request_id', 'created_by'])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return ['billing' => BillingCycle::class, 'starts_on' => 'date', 'ends_on' => 'date', 'amount' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function planModel(): ?SubscriptionPlan
    {
        return SubscriptionPlan::bySlug($this->plan);
    }

    public function upgradeRequest(): BelongsTo
    {
        return $this->belongsTo(UpgradeRequest::class);
    }

    /** Covers today. */
    public function scopeActive(Builder $query): Builder
    {
        $today = today()->toDateString();

        return $query->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today);
    }

    public function isActive(): bool
    {
        return today()->betweenIncluded($this->starts_on, $this->ends_on);
    }

    public function status(): string
    {
        return match (true) {
            $this->isActive() => 'active',
            $this->starts_on->isFuture() => 'upcoming',
            default => 'ended',
        };
    }
}
