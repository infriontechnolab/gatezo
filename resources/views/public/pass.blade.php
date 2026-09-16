@extends('layouts.public')
@section('content')
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-neutral-200">
        <div class="text-center">
            <div class="text-xs font-medium uppercase tracking-wider text-neutral-400">Entry pass</div>
            <div class="mt-1 text-xl font-bold">{{ $attendee->name }}</div>
            @if ($attendee->is_vip)
                <span class="mt-2 inline-block rounded-full bg-amber-100 px-3 py-0.5 text-xs font-semibold text-amber-800">VIP</span>
            @endif
        </div>
        <div class="mx-auto mt-5 w-full max-w-[280px] [&>svg]:h-auto [&>svg]:w-full">{!! $qrSvg !!}</div>
        <div class="mt-4 text-center font-mono text-2xl tracking-[0.3em]">{{ $pass->code }}</div>
        <p class="mt-1 text-center text-xs text-neutral-400">Show this at the gate. If the camera fails, tell the volunteer the code.</p>
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
