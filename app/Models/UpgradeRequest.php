<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An organizer choosing a paid plan. Pending until we've called, been paid and recorded the period, or dismissed it. */
#[Fillable(['user_id', 'event_id', 'plan', 'billing', 'phone', 'note', 'status'])]
class UpgradeRequest extends Model
{
    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Paid (or refused): close the request and, if paid, record the period.
     *
     * @param  array{plan?: string, billing?: ?string, starts_on?: mixed, ends_on?: mixed, amount?: ?int, payment_ref?: ?string, note?: ?string}  $period
     */
    public function resolve(string $status, User $by, array $period = []): ?Subscription
    {
        $subscription = null;
        if ($status === 'done') {
            $subscription = $this->user->subscriptions()->create([
                'plan' => $this->plan ?? 'pro',
                'billing' => $this->billing,
                ...$period,
                'upgrade_request_id' => $this->id,
                'created_by' => $by->id,
            ]);
        }
        $this->forceFill(['status' => $status, 'handled_by' => $by->id, 'handled_at' => now()])->save();

        return $subscription;
    }

    public function planModel(): ?SubscriptionPlan
    {
        return SubscriptionPlan::bySlug($this->plan);
    }

    /** "Pro, yearly · ₹24,999" */
    public function choiceLabel(): string
    {
        $plan = $this->planModel();
        if (! $plan) {
            return $this->plan ? ucfirst($this->plan) : 'Not chosen';
        }

        return $plan->name.($this->billing ? ', '.$this->billing.' · '.($plan->priceLabel($this->billing) ?? 'no price set') : '');
    }

    /** The number to call back: the one given on the form, else the account's. */
    public function contactPhone(): ?string
    {
        return $this->phone ?: $this->user->phone;
    }
}
