<?php

namespace App\Support;

use App\Filament\Pages\Upgrade;
use App\Models\Event;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;

/**
 * Plan limits, in one place. A plan limits only how many events an organizer creates; every
 * event can be any size, with any team. The numbers live in the plan catalogue (Ops → Plans).
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

    // ---- Events: the only thing a plan limits -------------------------------

    public static function eventLimit(User $user): ?int
    {
        return self::of($user)->max_events;
    }

    /**
     * Where the event count starts. Free counts forever (your first event). Paid plans count
     * per month, yearly ones included, with months running from the day the paid period
     * started; a plan Ops set by hand, with no dates, counts per calendar month.
     */
    public static function periodStart(User $user): ?Carbon
    {
        if (self::isFree($user)) {
            return null;
        }
        $started = $user->subscriptions()->active()->where('plan', $user->currentPlan())->min('starts_on');
        if ($started === null) {
            return today()->startOfMonth();
        }
        $started = Carbon::parse($started);

        return $started->copy()->addMonthsNoOverflow((int) floor($started->diffInMonths(today())));
    }

    public static function eventsUsed(User $user): int
    {
        $from = self::periodStart($user);

        return Event::where('created_by', $user->id)->when($from, fn ($q) => $q->where('created_at', '>=', $from))->count();
    }

    public static function canCreateEvent(User $user): bool
    {
        $limit = self::eventLimit($user);

        return $limit === null || self::eventsUsed($user) < $limit;
    }

    /** "1 event", "3 events a month", "Unlimited events". */
    public static function eventLimitLabel(SubscriptionPlan $plan): string
    {
        return match (true) {
            $plan->max_events === null => 'Unlimited events',
            $plan->isFree() => $plan->max_events.' '.str('event')->plural($plan->max_events),
            default => $plan->max_events.' '.str('event')->plural($plan->max_events).' a month',
        };
    }

    // ---- Upgrade ------------------------------------------------------------

    /** The in-panel plans page for an event (plan cards + request form). */
    public static function upgradePageUrl(Event $event): string
    {
        return Upgrade::getUrl(tenant: $event);
    }
}
