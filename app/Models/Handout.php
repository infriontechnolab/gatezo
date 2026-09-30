<?php

namespace App\Models;

use App\Enums\HandoutDecision;
use App\Enums\HandoutFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One goodies scan at a goodies counter. Refused scans are kept as a record but are not handouts. */
#[Fillable(['event_id', 'pass_id', 'gate_id', 'given_by', 'scanned_at', 'client_id', 'flag', 'decision'])]
class Handout extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'flag' => HandoutFlag::class,
            'decision' => HandoutDecision::class,
            'scanned_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /** Goodies actually handed over (clean, or given anyway after a warning). */
    public function scopeGiven(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNull('decision')->orWhere('decision', '!=', HandoutDecision::Refused));
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'given_by');
    }
}
