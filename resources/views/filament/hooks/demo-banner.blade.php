@if (config('gatezo.demo.enabled') && auth()->user()?->email === config('gatezo.demo.email'))
    <div class="demo-banner">
        <span class="demo-banner-dot"></span>
        <span>You're in the shared demo event. Change anything you like; it resets every night at {{ config('gatezo.demo.reset_at') }}.</span>
        <a href="{{ filament()->getRegistrationUrl() }}">Start your own event</a>
    </div>
@endif
