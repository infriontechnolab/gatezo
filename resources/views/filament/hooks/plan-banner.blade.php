@php
    use App\Support\Plan;
    $user = auth()->user();
    $event = filament()->getTenant();
    // Paid plan about to lapse: tell the event's owner a week ahead, so it isn't a surprise.
    $paidUntil = $user && $event && $event->created_by === $user->id ? $user->paidUntil() : null;
    $ending = $paidUntil && $paidUntil->lte(today()->addDays(7)) ? $paidUntil : null;
@endphp
@if ($ending)
    <div class="plan-banner">
        <span class="plan-banner-dot"></span>
        <span>
            <b>{{ Plan::of($user)->name }}</b> ·
            {{ $ending->isToday() ? 'ends today' : 'ends on '.$ending->format('j M') }}. Your events keep working; after that, new events need a plan.
        </span>
        <a href="{{ Plan::upgradePageUrl($event) }}">Renew</a>
    </div>
@endif
