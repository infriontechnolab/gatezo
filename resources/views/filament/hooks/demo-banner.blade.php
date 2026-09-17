@if (config('gatezo.demo.enabled') && auth()->user()?->email === config('gatezo.demo.email'))
    <div class="demo-banner">
        <span class="demo-banner-dot"></span>
        <span>You're in the shared demo event. Change anything you like; it resets every night at {{ config('gatezo.demo.reset_at') }}.</span>
        <a href="https://wa.me/{{ config('gatezo.whatsapp') }}?text={{ urlencode('Hi, I tried the Gatezo demo and want my own event.') }}">Start your own event</a>
    </div>
@endif
