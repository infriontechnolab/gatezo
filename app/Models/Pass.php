<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'attendee_id', 'code', 'revoked'])]
class Pass extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['revoked' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Pass $pass) {
            // Unambiguous alphabet (no 0/O/1/I) so volunteers can type it as a fallback.
            $pass->code ??= self::generateCode();
        });
    }

    public static function generateCode(int $length = 8): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function drawWinners(): HasMany
    {
        return $this->hasMany(DrawWinner::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }
}
