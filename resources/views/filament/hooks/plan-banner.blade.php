@php
    use App\Support\Plan;
    $user = auth()->user();
    $event = filament()->getTenant();
    // Only the event's owner sees this, and only once the registration cap is close (last 10%)
    // or hit: a plan with room to spare should feel like the full product.
    $owner = $user && $event && $event->created_by === $user->id;
    $left = $owner ? Plan::attendeesLeft($event) : null;
    $limit = $owner ? Plan::attendeeLimit($event) : null;
    $show = $left !== null && $left <= (int) ceil($limit * 0.1);
    // Pro that is about to lapse: tell the owner a week ahead, so the caps don't return by surprise.
    $paidUntil = ! $show && $user && $event && $event->created_by === $user->id ? $user->paidUntil() : null;
    $ending = $paidUntil && $paidUntil->lte(today()->addDays(7)) ? $paidUntil : null;
@endphp
@if ($show)
    <div class="plan-banner {{ $left === 0 ? 'plan-banner-full' : '' }}">
        <span class="plan-banner-dot"></span>
        <span>
            <b>{{ Plan::of($user)->name }} plan</b> ·
            @if ($left === 0)
                registration is full ({{ number_format($limit) }} of {{ number_format($limit) }}). New sign-ups are being turned away.
            @else
                {{ number_format($left) }} of {{ number_format($limit) }} registrations left.
            @endif
        </span>
        <a href="{{ Plan::upgradePageUrl($event) }}">See plans</a>
    </div>
@endif
@if ($ending)
    <div class="plan-banner">
        <span class="plan-banner-dot"></span>
        <span>
            <b>{{ Plan::of($user)->name }}</b> ·
            {{ $ending->isToday() ? 'ends today' : 'ends on '.$ending->format('j M') }}. After that, events are capped at {{ number_format(\App\Models\SubscriptionPlan::free()->max_attendees) }} registrations.
        </span>
        <a href="{{ Plan::upgradePageUrl($event) }}">Renew</a>
    </div>
@endif
