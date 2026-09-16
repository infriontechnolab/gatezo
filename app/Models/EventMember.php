<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['event_id', 'user_id', 'role'])]
class EventMember extends Pivot
{
    protected $table = 'event_members';

    public $incrementing = false;
}
