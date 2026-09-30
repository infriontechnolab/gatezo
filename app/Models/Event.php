<?php

namespace App\Models;

use App\Enums\CheckinDirection;
use App\Enums\EventType;
use App\Enums\HandoutFlag;
use App\Enums\KitStyle;
use App\Enums\MemberRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

#[Fillable([
    'slug', 'name', 'type', 'description', 'venue', 'accent_hex', 'logo_url', 'kit_style',
    'capacity', 'starts_at', 'ends_at', 'allow_self_register', 'allow_reentry', 'strict_passes',
    'roster_only', 'require_volunteer_approval', 'join_by_code',
    'goodies_enabled', 'goodies_name', 'goodies_stock', 'goodies_after_checkin', 'goodies_ticket_types',
])]
#[Hidden(['pass_secret'])]
class Event extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'kit_style' => KitStyle::class,
            'capacity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'allow_self_register' => 'boolean',
            'allow_reentry' => 'boolean',
            'strict_passes' => 'boolean',
            'roster_only' => 'boolean',
            'require_volunteer_approval' => 'boolean',
            'join_by_code' => 'boolean',
            'volunteer_code_version' => 'integer',
            'goodies_enabled' => 'boolean',
            'goodies_stock' => 'integer',
            'goodies_after_checkin' => 'boolean',
            'goodies_ticket_types' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Secrets are generated once, never mass-assigned.
        static::creating(function (Event $event) {
            $event->pass_secret ??= str()->random(48);
            $event->volunteer_code ??= str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $event->slug ??= str()->slug($event->name).'-'.str()->lower(str()->random(4));
        });

        // InnoDB refuses a cascade delete when a grandchild row is both cascaded (via event_id)
        // and set-null'd (via gate_id / attendee_id) in the same statement. Clear those tables
        // first; the remaining children cascade cleanly.
        static::deleting(function (Event $event) {
            $event->feedback()->delete();
            $event->checkins()->delete();
            $event->handouts()->delete();
            $event->dutyLogs()->delete();
            $event->shifts()->delete();
        });
    }

    /** Logo is uploaded to the `public` disk (served at /storage via the storage:link symlink). */
    public function logoUrl(): ?string
    {
        return $this->logo_url ? Storage::disk('public')->url($this->logo_url) : null;
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
            'goodies_enabled', 'goodies_name', 'goodies_stock', 'goodies_after_checkin', 'goodies_ticket_types',
        ]));
        $copy->name = $name;
        $copy->starts_at = $startsAt;
        $copy->ends_at = $endsAt;
        $copy->created_by = auth()->id() ?? $this->created_by;
        $copy->save(); // creating() hook mints slug, pass_secret, volunteer_code

        foreach ($source->gates as $gate) {
            $copy->gates()->create($gate->only(['name', 'code', 'is_entry', 'is_goodies']));
        }
        foreach ($source->stalls as $stall) {
            $copy->stalls()->create($stall->only(['name', 'description', 'logo_url', 'location', 'products', 'offers', 'vendor_user_id']));
        }
        $organizers = $this->members()->wherePivot('role', MemberRole::Organizer)->pluck('users.id');
        $copy->members()->attach($organizers->mapWithKeys(fn ($id) => [$id => ['role' => MemberRole::Organizer]])->all());

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
        $this->forceFill(['pass_secret' => str()->random(48)])->save();
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

    public function handouts(): HasMany
    {
        return $this->hasMany(Handout::class);
    }

    /** What the goodies are called on the scanner and in reports. */
    public function goodiesLabel(): string
    {
        return $this->goodies_name ?: 'Goodies';
    }

    /**
     * Server-side eligibility for one pass, in the order the volunteer should hear it.
     * Null = eligible. Never blocks: the scanner shows it and the volunteer decides.
     */
    public function goodiesFlag(Pass $pass): ?HandoutFlag
    {
        if ($this->handouts()->given()->where('pass_id', $pass->id)->exists()) {
            return HandoutFlag::AlreadyCollected;
        }
        if ($this->goodies_ticket_types && ! in_array($pass->attendee?->ticket_type?->value, $this->goodies_ticket_types, true)) {
            return HandoutFlag::TicketType;
        }
        if ($this->goodies_after_checkin && ! $pass->checkins()->where('direction', CheckinDirection::In)->exists()) {
            return HandoutFlag::NotCheckedIn;
        }

        return null;
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
