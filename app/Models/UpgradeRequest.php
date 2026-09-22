<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An organizer asking for Pro. Pending until someone in Ops flips their plan or dismisses it. */
#[Fillable(['user_id', 'event_id', 'note', 'status'])]
class UpgradeRequest extends Model
{
    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Pro granted (or refused): close the request and, if granted, upgrade the account. */
    public function resolve(string $status, User $by): void
    {
        if ($status === 'done') {
            $this->user->update(['plan' => 'pro']);
        }
        $this->forceFill(['status' => $status, 'handled_by' => $by->id, 'handled_at' => now()])->save();
    }
}
