<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /** Organizer of this event. Used by the print/report/export routes outside Filament. */
    public function manage(User $user, Event $event): bool
    {
        return $user->isOrganizerOf($event);
    }
}
