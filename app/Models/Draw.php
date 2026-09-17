<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

#[Fillable(['event_id', 'name', 'pool_source', 'filters', 'exclude_previous_winners', 'claim_minutes', 'alternates_per_prize', 'presentation', 'publish_results'])]
#[Hidden(['seed'])]
class Draw extends Model
{
    use HasFactory;

    public const POOLS = [
        'inside_now' => 'Inside now (latest scan is an entry)',
        'checked_in' => 'Checked in at least once',
        'registered' => 'Everyone registered',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'presentation' => 'array',
            'pool_snapshot' => 'array',
            'exclude_previous_winners' => 'boolean',
            'publish_results' => 'boolean',
            'claim_minutes' => 'integer',
            'alternates_per_prize' => 'integer',
            'run_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Commit to a seed before anyone can see the pool. The seed itself stays
        // hidden until the draw runs; the hash is shown on the results page.
        static::creating(function (Draw $draw) {
            $draw->seed ??= bin2hex(random_bytes(16));
            $draw->seed_hash ??= hash('sha256', $draw->seed);
            $draw->presentation ??= ['style' => 'roll', 'reveal_seconds' => 8, 'confetti' => true, 'show_phone_masked' => true];
            // Mirror the DB defaults so a just-created model behaves the same as a loaded one.
            $draw->pool_source ??= 'inside_now';
            $draw->exclude_previous_winners ??= true;
            $draw->publish_results ??= true;
            $draw->claim_minutes ??= 5;
            $draw->alternates_per_prize ??= 1;
            $draw->status ??= 'draft';
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(Prize::class)->orderBy('sort_order')->orderBy('id');
    }

    public function winners(): HasMany
    {
        return $this->hasMany(DrawWinner::class);
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }

    public function isRun(): bool
    {
        // A just-created model may not have the DB default loaded yet.
        return ($this->status ?? 'draft') !== 'draft';
    }

    /** The slot currently on stage (announced, not yet claimed/forfeited). */
    public function current(): ?DrawWinner
    {
        return $this->winners()->where('status', 'announced')->with(['prize', 'pass.attendee'])->orderByDesc('announced_at')->first();
    }

    public function presenterUrl(): string
    {
        return URL::signedRoute('draw.stage', [$this->event, $this]);
    }

    public static function maskPhone(?string $phone): ?string
    {
        if (! $phone || strlen($phone) < 6) {
            return null;
        }

        return substr($phone, 0, 2).str_repeat('x', strlen($phone) - 6).substr($phone, -4);
    }

    public static function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        if (count($parts) < 2) {
            return $name;
        }

        return $parts[0].' '.Str::upper(Str::substr(end($parts), 0, 1)).'.';
    }
}
