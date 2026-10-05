<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone who came to any of an organizer's events, keyed by phone. Kept in step by App\Services\VisitorBook. */
#[Fillable([
    'user_id', 'phone', 'name', 'email', 'events_count', 'last_event_id',
    'first_seen_at', 'last_seen_at', 'marketing_opt_in', 'marketing_opt_in_at',
])]
class Visitor extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'events_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'marketing_opt_in' => 'boolean',
            'marketing_opt_in_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lastEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'last_event_id');
    }
}
