<?php

namespace App\Services;

use App\Enums\AttendeeSource;
use App\Models\Attendee;
use App\Models\Event;
use App\Support\Phone;
use Illuminate\Validation\ValidationException;

/** One person registering for an event, from the public form or at the gate. */
class Registration
{
    /**
     * Returns the attendee with their pass; `wasRecentlyCreated` is false when the phone
     * already had a pass.
     *
     * @param  array{name: string, phone?: ?string, email?: ?string, marketing_opt_in?: bool}  $data
     *
     * @throws ValidationException
     */
    public function register(Event $event, array $data, AttendeeSource $source): Attendee
    {
        $attendee = $this->find($event, $data);
        if ($attendee === null) {
            $attendee = $event->attendees()->create($data + ['source' => $source]);
        } elseif (array_key_exists('marketing_opt_in', $data)) {
            // Coming back for their pass and answering again: the newer answer is the one that counts.
            $attendee->update(['marketing_opt_in' => $data['marketing_opt_in']]);
        }
        if ($attendee->pass === null) {
            $attendee->setRelation('pass', $attendee->pass()->create(['event_id' => $event->id]));
        }

        return $attendee;
    }

    /**
     * Someone who already has a pass here, by phone or email.
     *
     * @param  array{name: string, phone?: ?string, email?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function find(Event $event, array $data): ?Attendee
    {
        // Same phone (or email) at the same event = same person: hand back the existing pass
        // rather than minting a second one ("lost my pass" is the #1 gate question, and it's how
        // people imported from another system collect theirs). The name has to match too, so
        // knowing someone's number is not enough to pull up their pass.
        $attendee = null;
        $phone = Phone::normalise($data['phone'] ?? null);
        $email = filled($data['email'] ?? null) ? str()->lower($data['email']) : null;
        foreach (['phone' => $phone, 'email' => $email] as $field => $value) {
            if ($attendee !== null || $value === null) {
                continue;
            }
            $attendee = $event->attendees()->where($field, $value)->first();
            if ($attendee && ! Phone::sameFirstName($attendee->name, $data['name'])) {
                throw ValidationException::withMessages([
                    $field => "A pass already exists for this {$field} under a different name. Use the name you registered with, or ask at the desk.",
                ]);
            }
        }

        return $attendee;
    }
}
