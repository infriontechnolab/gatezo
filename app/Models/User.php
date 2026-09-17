<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'phone', 'password'])]
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
        ];
    }

    public function isVolunteerAccount(): bool
    {
        return str_ends_with($this->email, '@'.self::VOLUNTEER_DOMAIN);
    }

    /** Events this user has any role in. */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function organizedEvents(): BelongsToMany
    {
        return $this->events()->wherePivot('role', 'organizer');
    }

    public function isOrganizerOf(Event $event): bool
    {
        return $this->organizedEvents()->whereKey($event->id)->exists();
    }

    // ---- Filament ---------------------------------------------------------

    public function canAccessPanel(Panel $panel): bool
    {
        return ! $this->isVolunteerAccount();
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
