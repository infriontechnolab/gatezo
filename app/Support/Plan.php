<?php

namespace App\Support;

use App\Models\Event;
use App\Models\User;

/**
 * The free-plan caps, in one place. An event is governed by the plan of the user who
 * created it, so a Pro organizer inviting a free-plan friend does not shrink the event.
 * Events with no creator (seeded, or made before plans existed) are uncapped.
 */
final class Plan
{
    public static function label(string $plan): string
    {
        return config("gatezo.plans.{$plan}.label", ucfirst($plan));
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(config('gatezo.plans'));
    }

    public static function isFree(User $user): bool
    {
        return $user->plan === 'free';
    }

    // ---- Events per organizer ---------------------------------------------

    public static function eventLimit(User $user): ?int
    {
        return config("gatezo.plans.{$user->plan}.events");
    }

    public static function eventsUsed(User $user): int
    {
        return Event::where('created_by', $user->id)->count();
    }

    public static function canCreateEvent(User $user): bool
    {
        $limit = self::eventLimit($user);

        return $limit === null || self::eventsUsed($user) < $limit;
    }

    // ---- Registrations per event ------------------------------------------

    public static function attendeeLimit(Event $event): ?int
    {
        $owner = $event->creator;

        return $owner ? config("gatezo.plans.{$owner->plan}.attendees") : null;
    }

    public static function attendeesLeft(Event $event): ?int
    {
        $limit = self::attendeeLimit($event);

        return $limit === null ? null : max(0, $limit - $event->attendees()->count());
    }

    public static function isFull(Event $event): bool
    {
        return self::attendeesLeft($event) === 0;
    }

    // ---- Team per event -----------------------------------------------------

    public static function teamLimit(Event $event): ?int
    {
        $owner = $event->creator;

        return $owner ? config("gatezo.plans.{$owner->plan}.team") : null;
    }

    public static function canInvite(Event $event): bool
    {
        $limit = self::teamLimit($event);

        return $limit === null || $event->members()->wherePivot('role', 'organizer')->count() < $limit;
    }

    // ---- Upgrade ------------------------------------------------------------

    /** WhatsApp link with the account already named, so the chat starts with the facts. */
    public static function upgradeUrl(?User $user = null, ?Event $event = null): string
    {
        $who = $user ? " I'm {$user->name} ({$user->email})" : '';
        $what = $event ? " for \"{$event->name}\"" : '';

        return 'https://wa.me/'.config('gatezo.whatsapp').'?text='.urlencode("Hi, I want to upgrade to Gatezo Pro{$what}.{$who}");
    }
}
