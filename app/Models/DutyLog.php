<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'volunteer_id', 'gate_id', 'status', 'at'])]
class DutyLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['at' => 'datetime'];
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
}
