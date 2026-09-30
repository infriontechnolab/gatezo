<?php

namespace App\Models;

use App\Enums\VolunteerJoinResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per attempt to join the scanner, successful or not. */
#[Fillable(['event_id', 'user_id', 'name', 'code', 'result', 'ip', 'device', 'user_agent', 'created_at'])]
class VolunteerJoin extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['result' => VolunteerJoinResult::class, 'created_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
