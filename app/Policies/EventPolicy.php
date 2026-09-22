<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Support\Plan;

class EventPolicy
{
    /**
     * Filament checks this before showing "Create event" in the switcher and before
     * serving the RegisterEvent page; the dashboard's Duplicate action checks it too.
     */
    public function create(User $user): bool
    {
        return Plan::canCreateEvent($user);
    }

    /** Organizer of this event. Used by the print/report/export routes outside Filament. */
    public function manage(User $user, Event $event): bool
    {
        return $user->isOrganizerOf($event);
    }
}
