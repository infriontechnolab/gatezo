<?php

namespace App\Observers;

use App\Models\Attendee;
use App\Services\VisitorBook;

class AttendeeObserver
{
    public function __construct(private VisitorBook $visitors) {}

    public function saving(Attendee $attendee): void
    {
        if ($attendee->isDirty('marketing_opt_in')) {
            $attendee->marketing_opt_in_at = $attendee->marketing_opt_in === null ? null : now();
        }
    }

    public function saved(Attendee $attendee): void
    {
        if ($attendee->wasRecentlyCreated || $attendee->wasChanged(['phone', 'name', 'email', 'marketing_opt_in'])) {
            $this->visitors->syncAttendee($attendee);
        }
    }

    public function deleted(Attendee $attendee): void
    {
        $this->visitors->syncAttendee($attendee);
    }
}
