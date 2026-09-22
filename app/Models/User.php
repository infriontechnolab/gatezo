<?php

namespace App\Models;

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
        return $this->events()->wherePivot('role', 'organizer');
    }

    /** Volunteer membership pivot for an event, if any. */
    public function volunteerPivot(Event $event): ?object
    {
        return $this->events()->wherePivot('role', 'volunteer')->whereKey($event->id)->first()?->pivot;
    }

    public function isOrganizerOf(Event $event): bool
    {
        return $this->organizedEvents()->whereKey($event->id)->exists();
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

    public function getTenants(Panel $panel): Collection
    {
        return $this->organizedEvents;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Event && $this->isOrganizerOf($tenant);
    }
}
