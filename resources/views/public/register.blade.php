@extends('layouts.public')
@section('content')
    <h1 class="text-2xl font-bold">Get your pass</h1>
    @if ($event->starts_at)
        <p class="mt-1 text-neutral-500">{{ $event->starts_at->format('D, j M · g:i A') }}</p>
    @endif
    @if ($event->description)
        <p class="mt-4 text-neutral-700">{{ $event->description }}</p>
    @endif

    @unless ($event->allow_self_register)
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">Registration is closed for this event.</div>
    @else
        <form method="post" action="{{ route('event.register', $event) }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span class="text-sm font-medium">Your name</span>
                <input name="name" value="{{ old('name') }}" required autocomplete="name" class="mt-1 w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-[var(--accent)] focus:outline-none">
                @error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block">
                <span class="text-sm font-medium">Phone <span class="text-neutral-400">(so you can find this pass again)</span></span>
                <input name="phone" value="{{ old('phone') }}" type="tel" inputmode="tel" autocomplete="tel" placeholder="10-digit mobile" pattern="[0-9+()\s.-]{8,25}" title="Digits only, 10 for India or with country code" class="mt-1 w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-[var(--accent)] focus:outline-none">
                @error('phone')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <button class="w-full rounded-xl py-3.5 text-base font-semibold text-white" style="background: var(--accent)">Get my pass</button>
            <p class="text-center text-xs text-neutral-400">Free event. No app, no account.</p>
            <p class="rounded-xl bg-neutral-100 px-4 py-3 text-center text-sm text-neutral-600"><b>Already registered?</b> Enter the same name and phone and we'll show your existing pass.</p>
        </form>
    @endunless
@endsection
