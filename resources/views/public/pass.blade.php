@extends('layouts.public')
@push('head')
    @if ($event->draws()->where('status', 'ready')->exists() && ! $event->strict_passes)<meta http-equiv="refresh" content="20">@endif
    @if ($event->strict_passes)
    <script>
        // Polls /pass/{code}/qr as each 30 s slot ends. Two missed refreshes = stale (offline).
        document.addEventListener('alpine:init', () => Alpine.data('livePass', (url, left) => ({
            left, stale: false, _miss: 0,
            start() {
                setInterval(() => { this.left -= 1; if (this.left <= 0) this.refresh(); }, 1000);
                window.addEventListener('online', () => this.refresh());
            },
            async refresh() {
                this.left = 30;
                try {
                    const r = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                    if (!r.ok) throw new Error(r.status);
                    const d = await r.json();
                    this.$refs.qr.innerHTML = d.svg; this.left = d.seconds_left; this.stale = false; this._miss = 0;
                } catch { this._miss += 1; this.stale = this._miss >= 2; }
            },
        })));
    </script>
    @endif
@endpush
@section('content')
    @if (session('existing_pass'))
        <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">Welcome back. This is the pass you already had; nothing new was created.</div>
    @endif
    @if ($win)
        <div class="mb-4 rounded-2xl p-5 text-white shadow-lg" style="background: var(--accent)">
            <div class="text-xs font-semibold uppercase tracking-[0.2em] opacity-80">{{ $win->status === 'claimed' ? 'You won' : 'You have been drawn' }}</div>
            <div class="mt-1 text-2xl font-bold">{{ $win->prize->name }}</div>
            @if ($win->status === 'announced')
                <div class="mt-2 text-sm opacity-90">Come to the stage by <b>{{ $win->claim_deadline?->format('g:i A') }}</b> and show this pass.</div>
            @else
                <div class="mt-2 text-sm opacity-90">Claimed {{ $win->claimed_at?->format('g:i A') }}. Congratulations!</div>
            @endif
        </div>
    @endif
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-neutral-200">
        <div class="text-center">
            <div class="text-xs font-medium uppercase tracking-wider text-neutral-400">Entry pass</div>
            <div class="mt-1 text-xl font-bold">{{ $attendee->name }}</div>
            @if ($attendee->is_vip)
                <span class="mt-2 inline-block rounded-full bg-amber-100 px-3 py-0.5 text-xs font-semibold text-amber-800">VIP</span>
            @endif
        </div>
        @if ($event->strict_passes)
            {{-- Live pass: a new QR every 30 s, so a screenshot is worthless within a minute. --}}
            <div x-data="livePass(@js(route('pass.qr', $pass)), {{ $secondsLeft }})" x-init="start()">
                <div class="relative mx-auto mt-5 w-full max-w-[280px] [&>svg]:h-auto [&>svg]:w-full" :class="stale && 'opacity-40'" x-ref="qr">{!! $qrSvg !!}</div>
                <div class="mt-3 flex items-center justify-center gap-2 text-xs font-medium" :class="stale ? 'text-red-600' : 'text-neutral-500'">
                    <span class="inline-block h-2 w-2 rounded-full" :class="stale ? 'bg-red-500' : 'bg-emerald-500'"></span>
                    <span x-text="stale ? 'Reconnect to refresh your pass' : 'Live pass · refreshes in ' + left + ' s'"></span>
                </div>
            </div>
            <div class="mt-4 text-center font-mono text-2xl tracking-[0.3em]">{{ $pass->code }}</div>
            <p class="mt-1 text-center text-xs text-neutral-400">Keep this page open at the gate. Screenshots stop working after a minute. If the camera fails, tell the volunteer the code.</p>
        @else
            <div class="mx-auto mt-5 w-full max-w-[280px] [&>svg]:h-auto [&>svg]:w-full">{!! $qrSvg !!}</div>
            <div class="mt-4 text-center font-mono text-2xl tracking-[0.3em]">{{ $pass->code }}</div>
            <p class="mt-1 text-center text-xs text-neutral-400">Show this at the gate. If the camera fails, tell the volunteer the code.</p>
        @endif
    </div>

    <div class="mt-5 grid grid-cols-2 gap-3">
        <a href="https://wa.me/?text={{ urlencode('My pass for '.$event->name.': '.url()->current()) }}" class="rounded-xl border border-neutral-300 bg-white py-3 text-center text-sm font-medium">Send to WhatsApp</a>
        <button onclick="navigator.share ? navigator.share({title: @js($event->name), url: location.href}) : (navigator.clipboard.writeText(location.href), this.textContent = 'Copied')" class="rounded-xl border border-neutral-300 bg-white py-3 text-center text-sm font-medium">Share / copy link</button>
    </div>
    @if ($event->stalls()->exists())
    <form method="post" action="{{ route('pass.consent', $pass) }}" class="mt-4 flex items-center justify-between rounded-xl bg-white px-4 py-3 ring-1 ring-neutral-200">
        @csrf
        <input type="hidden" name="share_contact" value="{{ $attendee->share_contact ? 0 : 1 }}">
        <div class="text-sm">
            <div class="font-medium">Allow stalls to contact me</div>
            <div class="text-xs text-neutral-500">Stalls you visit can save your number when they scan your pass.</div>
        </div>
        <button class="ml-3 h-7 w-12 shrink-0 rounded-full transition {{ $attendee->share_contact ? 'bg-emerald-500' : 'bg-neutral-300' }}" aria-pressed="{{ $attendee->share_contact ? 'true' : 'false' }}">
            <span class="block h-6 w-6 rounded-full bg-white shadow transition {{ $attendee->share_contact ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
        </button>
    </form>
    @endif
    <a href="{{ route('event.feedback', $event) }}?pass={{ $pass->code }}" class="mt-3 block text-center text-sm text-neutral-500 underline">Leave feedback after the event</a>
    <p class="mt-6 text-center text-xs text-neutral-400">Tip: add this page to your home screen so it opens without internet.</p>
@endsection
