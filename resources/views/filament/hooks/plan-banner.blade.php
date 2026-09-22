@php
    use App\Support\Plan;
    $user = auth()->user();
    $event = filament()->getTenant();
    // Only the event's owner sees this, and only once the registration cap is close (last 10%)
    // or hit: a quiet free plan should feel like the full product.
    $owner = $user?->onFreePlan() && $event && $event->created_by === $user->id;
    $left = $owner ? Plan::attendeesLeft($event) : null;
    $limit = $owner ? Plan::attendeeLimit($event) : null;
    $show = $left !== null && $left <= (int) ceil($limit * 0.1);
@endphp
@if ($show)
    <div class="plan-banner {{ $left === 0 ? 'plan-banner-full' : '' }}">
        <span class="plan-banner-dot"></span>
        <span>
            <b>Free plan</b> ·
            @if ($left === 0)
                registration is full ({{ number_format($limit) }} of {{ number_format($limit) }}). New sign-ups are being turned away.
            @else
                {{ number_format($left) }} of {{ number_format($limit) }} registrations left.
            @endif
        </span>
        <a href="{{ Plan::upgradeUrl($user, $event) }}" target="_blank" rel="noopener">Upgrade to Pro on WhatsApp</a>
    </div>
@endif
