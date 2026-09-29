<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'name', 'code', 'is_entry', 'is_goodies'])]
class Gate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_entry' => 'boolean', 'is_goodies' => 'boolean'];
    }

    protected static function booted(): void
    {
        // A goodies counter is its own post: scans there hand out goodies, never admit anyone.
        static::saving(function (Gate $gate) {
            if ($gate->is_goodies) {
                $gate->is_entry = false;
            }
        });
    }

    /** URL printed on the gate/zone sign. Scanner app recognises it; a camera app lands on the join page. */
    public function signUrl(): string
    {
        return route('scan.gate', [$this->event->slug, $this->code]);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function handouts(): HasMany
    {
        return $this->hasMany(Handout::class);
    }

    public function dutyLogs(): HasMany
    {
        return $this->hasMany(DutyLog::class);
    }
}
