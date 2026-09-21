<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

#[Fillable([
    'slug', 'name', 'type', 'description', 'venue', 'accent_hex', 'logo_url', 'kit_style',
    'capacity', 'starts_at', 'ends_at', 'allow_self_register', 'allow_reentry', 'strict_passes',
    'roster_only', 'require_volunteer_approval',
])]
#[Hidden(['pass_secret'])]
class Event extends Model
{
    use HasFactory;

    /** Print kit templates. Classic is black on white for any printer; the others want colour. */
    public const KIT_STYLES = [
        'classic' => 'Classic · black on white, prints anywhere',
        'bold' => 'Bold · accent header bands, rounded QR frames',
        'festival' => 'Festival · full colour background',
    ];

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
            'strict_passes' => 'boolean',
            'roster_only' => 'boolean',
            'require_volunteer_approval' => 'boolean',
            'volunteer_code_version' => 'integer',
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

        // InnoDB refuses a cascade delete when a grandchild row is both cascaded (via event_id)
        // and set-null'd (via gate_id / attendee_id) in the same statement. Clear those tables
        // first; the remaining children cascade cleanly.
        static::deleting(function (Event $event) {
            $event->feedback()->delete();
            $event->checkins()->delete();
            $event->dutyLogs()->delete();
            $event->shifts()->delete();
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
            'type', 'description', 'venue', 'accent_hex', 'logo_url', 'kit_style', 'capacity', 'allow_self_register', 'allow_reentry', 'strict_passes',
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

    /** Signed link for the read-only gate board. Rotating the pass secret does not affect it. */
    public function boardUrl(): string
    {
        return URL::signedRoute('board.show', $this);
    }

    /** New 6-digit code; every current volunteer session stops working until they rejoin. */
    public function rotateVolunteerCode(): void
    {
        $this->forceFill([
            'volunteer_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'volunteer_code_version' => ($this->volunteer_code_version ?? 1) + 1,
        ])->save();
    }

    public function volunteerJoins(): HasMany
    {
        return $this->hasMany(VolunteerJoin::class);
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
            ->withPivot(['role', 'approved_at', 'kicked_at'])
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

    public function draws(): HasMany
    {
        return $this->hasMany(Draw::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }
}
