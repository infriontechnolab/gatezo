<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A planned post for a volunteer: "Ravi, Main Gate, 6–8 pm". Assigned by name because
 * volunteers only get a user row when they join on event day; see linkVolunteer().
 * Actual presence lives in duty_logs.
 */
#[Fillable(['event_id', 'volunteer_id', 'volunteer_name', 'gate_id', 'label', 'starts_at', 'ends_at'])]
#[Hidden(['invite_token'])]
class Shift extends Model
{
    use HasFactory;

    /** How late a volunteer can be before the shift shows "Late". */
    public const GRACE_MINUTES = 15;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'invite_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every roster entry gets a personal scanner link; see inviteUrl().
        static::creating(function (self $shift) {
            $shift->invite_token ??= Str::random(40);
        });
        // A copied shift is a new invitation: never share a token or its used-state.
        static::replicating(function (self $shift) {
            $shift->invite_token = Str::random(40);
            $shift->invite_used_at = null;
            $shift->invite_device = null;
        });
    }

    // ---- Personal invite link ------------------------------------------------

    /**
     * Opens the scanner as this volunteer, no code needed. Binds to the first phone that
     * uses it (invite_device), so a forwarded link is dead on the second phone.
     */
    public function inviteUrl(): string
    {
        return route('scan.invite', $this->invite_token);
    }

    /** New token, old link dead. Also for "I sent it to the wrong person". */
    public function resetInvite(): void
    {
        $this->forceFill(['invite_token' => Str::random(40), 'invite_used_at' => null, 'invite_device' => null])->save();
    }

    /** Links stop working a day after the event ends (or the shift, if the event has no end). */
    public function inviteExpired(): bool
    {
        $end = $this->event->ends_at ?? $this->ends_at;

        return $end !== null && now()->gt($end->copy()->addDay());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    /** Names are compared loosely: "ravi patel" == "Ravi  Patel". */
    public static function normaliseName(string $name): string
    {
        return Str::lower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    public function scopeForName(Builder $query, string $name): Builder
    {
        return $query->whereRaw('LOWER(TRIM(volunteer_name)) = ?', [self::normaliseName($name)]);
    }

    /**
     * Called when a volunteer joins: attach every unlinked shift with their name.
     * Returns the shift that is current (or next) for them, if any.
     */
    public static function linkVolunteer(Event $event, User $volunteer): ?self
    {
        $event->shifts()->forName($volunteer->name)->whereNull('volunteer_id')->update(['volunteer_id' => $volunteer->id]);

        return self::currentFor($event, $volunteer);
    }

    /** The shift happening now, else the next one today, else null. */
    public static function currentFor(Event $event, User $volunteer): ?self
    {
        $shifts = $event->shifts()->where('volunteer_id', $volunteer->id)->with('gate')->orderBy('starts_at')->get();

        return $shifts->first(fn (self $s) => $s->isActiveAt(now()))
            ?? $shifts->first(fn (self $s) => $s->starts_at && $s->starts_at->isFuture())
            ?? null;
    }

    /** For boards/reports: the current or next shift, else the most recent one. */
    public static function rosteredFor(Event $event, User $volunteer): ?self
    {
        return self::currentFor($event, $volunteer)
            ?? $event->shifts()->where('volunteer_id', $volunteer->id)->with('gate')->orderByDesc('ends_at')->first();
    }

    public function isActiveAt(\DateTimeInterface $at): bool
    {
        return (! $this->starts_at || $this->starts_at <= $at) && (! $this->ends_at || $this->ends_at >= $at);
    }

    /**
     * Plan vs reality. Uses the volunteer's latest duty log:
     *   upcoming  starts in the future
     *   starting  window just opened (inside the grace period), not scanned in yet
     *   on_duty   inside the window, latest duty log is "on"
     *   elsewhere inside the window, on duty but at a different post
     *   late      window started > GRACE_MINUTES ago, no duty yet
     *   missed    window over, never went on duty during it
     *   done      window over, was on duty during it
     *   unlinked  nobody with this name has joined yet
     */
    public function status(): string
    {
        $now = now();
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'upcoming';
        }
        if (! $this->volunteer_id) {
            if ($this->ends_at && $this->ends_at->isPast()) {
                return 'missed';
            }

            return $this->starts_at && $now->diffInMinutes($this->starts_at, true) > self::GRACE_MINUTES ? 'late' : 'unlinked';
        }

        $logs = DutyLog::where('event_id', $this->event_id)->where('volunteer_id', $this->volunteer_id);
        $latest = (clone $logs)->latest('at')->first();

        if ($this->ends_at && $this->ends_at->isPast()) {
            $wasOn = (clone $logs)->where('status', 'on')
                ->where('at', '<=', $this->ends_at)
                ->when($this->starts_at, fn ($q) => $q->where('at', '>=', $this->starts_at->subMinutes(60)))
                ->exists();

            return $wasOn ? 'done' : 'missed';
        }

        if (! $latest || $latest->status !== 'on') {
            return $this->starts_at && $now->diffInMinutes($this->starts_at, true) > self::GRACE_MINUTES ? 'late' : 'starting';
        }

        return ($this->gate_id && $latest->gate_id && $latest->gate_id !== $this->gate_id) ? 'elsewhere' : 'on_duty';
    }
}
