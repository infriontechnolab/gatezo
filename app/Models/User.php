<?php

namespace App\Models;

use App\Enums\MemberRole;
use App\Enums\UpgradeRequestStatus;
use App\Support\Plan;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'phone', 'plan', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Volunteers are synthetic users minted from the 6-digit code; they never log into the panel. */
    public const VOLUNTEER_DOMAIN = 'volunteer.gatezo.local';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function onFreePlan(): bool
    {
        return Plan::isFree($this);
    }

    /**
     * The plan in force today: the highest-ranked paid period covering today, otherwise the
     * base plan. Overlaps only happen on an upgrade mid-period, so the better plan wins.
     */
    public function currentPlan(): string
    {
        $paid = $this->subscriptions()->active()->pluck('plan')
            ->sortByDesc(fn (string $slug) => SubscriptionPlan::bySlug($slug)?->sort ?? -1)->first();

        return $paid ?? $this->plan ?? SubscriptionPlan::FREE; // plan is null until reloaded: the column defaults to free
    }

    /** Last paid day, following back-to-back renewals. Null when nothing paid covers today (free, or a comped base plan). */
    public function paidUntil(): ?Carbon
    {
        $until = $this->subscriptions()->active()->max('ends_on');
        if (! $until) {
            return null;
        }
        while ($next = $this->subscriptions()->whereDate('starts_on', '<=', Carbon::parse($until)->addDay())->whereDate('ends_on', '>', $until)->max('ends_on')) {
            $until = $next;
        }

        return Carbon::parse($until);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** On a paid plan today, from a dated period or a comped base plan. */
    public function scopePaying(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('plan', '!=', SubscriptionPlan::FREE)->orWhereHas('subscriptions', fn (Builder $s) => $s->active()));
    }

    public function isVolunteerAccount(): bool
    {
        return str_ends_with($this->email, '@'.self::VOLUNTEER_DOMAIN);
    }

    /** Events this user has any role in. */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_members')
            ->withPivot(['role', 'approved_at', 'kicked_at'])
            ->withTimestamps();
    }

    public function organizedEvents(): BelongsToMany
    {
        return $this->events()->wherePivot('role', MemberRole::Organizer);
    }

    /** Volunteer membership pivot for an event, if any. */
    public function volunteerPivot(Event $event): ?object
    {
        return $this->events()->wherePivot('role', MemberRole::Volunteer)->where('events.id', $event->id)->first()?->pivot;
    }

    public function isOrganizerOf(Event $event): bool
    {
        return $this->organizedEvents()->where('events.id', $event->id)->exists();
    }

    // ---- Filament ---------------------------------------------------------

    /** Staff use /ops and see organizers' panels only via "log in as"; organizers use /admin. */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'ops') {
            return (bool) $this->is_admin;
        }

        return ! $this->is_admin && ! $this->isVolunteerAccount();
    }

    /** Real organizer accounts: not the synthetic volunteer users, not staff. */
    public function scopeOrganizers(Builder $query): Builder
    {
        return $query->where('email', 'not like', '%@'.self::VOLUNTEER_DOMAIN)->where('is_admin', false);
    }

    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function upgradeRequests(): HasMany
    {
        return $this->hasMany(UpgradeRequest::class);
    }

    public function pendingUpgradeRequest(): ?UpgradeRequest
    {
        return $this->upgradeRequests()->where('status', UpgradeRequestStatus::Pending)->latest()->first();
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->organizedEvents;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Event && $this->isOrganizerOf($tenant);
    }
}
