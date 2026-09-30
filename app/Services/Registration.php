<?php

namespace App\Services;

use App\Enums\AttendeeSource;
use App\Models\Attendee;
use App\Models\Event;
use App\Support\Phone;
use App\Support\Plan;
use Illuminate\Validation\ValidationException;

/** One person registering for an event, from the public form or at the gate. */
class Registration
{
    /**
     * Returns the attendee with their pass; `wasRecentlyCreated` is false when the phone
     * already had a pass.
     *
     * @param  array{name: string, phone?: ?string, email?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function register(Event $event, array $data, AttendeeSource $source): Attendee
    {
        // Same phone at the same event = same person: hand back the existing pass rather
        // than minting a second one ("lost my pass" is the #1 gate question). The name has
        // to match too, so knowing someone's number is not enough to pull up their pass.
        $attendee = null;
        $phone = Phone::normalise($data['phone'] ?? null);
        if ($phone !== null) {
            $attendee = $event->attendees()->where('phone', $phone)->first();
            if ($attendee && ! Phone::sameFirstName($attendee->name, $data['name'])) {
                throw ValidationException::withMessages([
                    'phone' => 'A pass already exists for this number under a different name. Use the name you registered with, or ask at the desk.',
                ]);
            }
        }
        // Free-plan cap. Checked after the lookup so someone who already has a pass can still
        // find it once the event is full.
        if ($attendee === null && Plan::isFull($event)) {
            throw ValidationException::withMessages(['name' => 'Registration is full for this event. Ask the organizer at the desk.']);
        }
        $attendee ??= $event->attendees()->create($data + ['source' => $source]);
        if ($attendee->pass === null) {
            $attendee->setRelation('pass', $attendee->pass()->create(['event_id' => $event->id]));
        }

        return $attendee;
    }
}
