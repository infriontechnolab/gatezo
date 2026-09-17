<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['draw_id', 'prize_id', 'pass_id', 'slot', 'rank', 'status', 'announced_at', 'claim_deadline', 'claimed_at', 'verified_by'])]
class DrawWinner extends Model
{
    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'rank' => 'integer',
            'announced_at' => 'datetime',
            'claim_deadline' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'announced' && $this->claim_deadline && $this->claim_deadline->isPast();
    }
}
