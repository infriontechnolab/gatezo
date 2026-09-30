<?php

namespace App\Support;

use App\Enums\MemberRole;
use App\Filament\Pages\Upgrade;
use App\Models\Event;
use App\Models\SubscriptionPlan;
use App\Models\User;

/**
 * Plan caps, in one place. The numbers live in the plan catalogue (Ops → Plans). An event is governed by the plan of the user who
 * created it, so a Pro organizer inviting a free-plan friend does not shrink the event.
 * Events with no creator (seeded, or made before plans existed) are uncapped.
 */
final class Plan
{
    public static function label(string $plan): string
    {
        return SubscriptionPlan::bySlug($plan)?->name ?? ucfirst($plan);
    }

    /** @return list<string> */
    public static function names(): array
    {
        return SubscriptionPlan::catalogue()->pluck('slug')->all();
    }

    /** The plan in force for a user today; an unknown slug falls back to Free's caps. */
    public static function of(User $user): SubscriptionPlan
    {
        return SubscriptionPlan::bySlug($user->currentPlan()) ?? SubscriptionPlan::free();
    }

    public static function isFree(User $user): bool
    {
        return $user->currentPlan() === SubscriptionPlan::FREE;
    }

    // ---- Events per organizer ---------------------------------------------

    public static function eventLimit(User $user): ?int
    {
        return self::of($user)->max_events;
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

        return $owner ? self::of($owner)->max_attendees : null;
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

        return $owner ? self::of($owner)->max_team : null;
    }

    public static function canInvite(Event $event): bool
    {
        $limit = self::teamLimit($event);

        return $limit === null || $event->members()->wherePivot('role', MemberRole::Organizer)->count() < $limit;
    }

    // ---- Upgrade ------------------------------------------------------------

    /** The in-panel plans page for an event (plan cards + request form). */
    public static function upgradePageUrl(Event $event): string
    {
        return Upgrade::getUrl(tenant: $event);
    }
}
