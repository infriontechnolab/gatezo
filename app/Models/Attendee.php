<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['event_id', 'name', 'phone', 'email', 'ticket_type', 'is_vip', 'share_contact', 'source', 'extra'])]
class Attendee extends Model
{
    use HasFactory;

    /** Every path (form, CSV, panel) stores the same shape, so "find my pass" by phone works. */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => Phone::normalise($v));
    }

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
            'share_contact' => 'boolean',
            'extra' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function pass(): HasOne
    {
        return $this->hasOne(Pass::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class);
    }
}
