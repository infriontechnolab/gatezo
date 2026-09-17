<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'slug', 'name', 'type', 'description', 'venue', 'accent_hex', 'logo_url',
    'capacity', 'starts_at', 'ends_at', 'allow_self_register', 'allow_reentry',
])]
#[Hidden(['pass_secret'])]
class Event extends Model
{
    use HasFactory;

    public const TYPES = [
        'community' => 'Community gathering',
        'sports' => 'Sports tournament',
        'festival' => 'Festival / fair',
        'workshop' => 'Workshop / training',
        'religious' => 'Religious event',
        'college' => 'College event',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'allow_self_register' => 'boolean',
            'allow_reentry' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Secrets are generated once, never mass-assigned.
        static::creating(function (Event $event) {
            $event->pass_secret ??= Str::random(48);
            $event->volunteer_code ??= str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $event->slug ??= Str::slug($event->name).'-'.Str::lower(Str::random(4));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Copy this event's setup (gates, stalls, organizers) into a new event with fresh
     * secrets and no attendees. "Same as last year" in one click.
     */
    public function duplicate(string $name, ?\DateTimeInterface $startsAt = null, ?\DateTimeInterface $endsAt = null): self
    {
        $source = $this->fresh(); // pick up DB defaults the in-memory model may not have
        $copy = new self($source->only([
            'type', 'description', 'venue', 'accent_hex', 'logo_url', 'capacity', 'allow_self_register', 'allow_reentry',
        ]));
        $copy->name = $name;
        $copy->starts_at = $startsAt;
        $copy->ends_at = $endsAt;
        $copy->created_by = auth()->id() ?? $this->created_by;
        $copy->save(); // creating() hook mints slug, pass_secret, volunteer_code

        foreach ($source->gates as $gate) {
            $copy->gates()->create($gate->only(['name', 'code', 'is_entry']));
        }
        foreach ($source->stalls as $stall) {
            $copy->stalls()->create($stall->only(['name', 'description', 'logo_url', 'location', 'products', 'offers', 'vendor_user_id']));
        }
        $organizers = $this->members()->wherePivot('role', 'organizer')->pluck('users.id');
        $copy->members()->attach($organizers->mapWithKeys(fn ($id) => [$id => ['role' => 'organizer']])->all());

        return $copy;
    }

    /** Rotate to invalidate every pass issued so far. */
    public function rotatePassSecret(): void
    {
        $this->forceFill(['pass_secret' => Str::random(48)])->save();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function gates(): HasMany
    {
        return $this->hasMany(Gate::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function passes(): HasMany
    {
        return $this->hasMany(Pass::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function stalls(): HasMany
    {
        return $this->hasMany(Stall::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function dutyLogs(): HasMany
    {
        return $this->hasMany(DutyLog::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }
}
