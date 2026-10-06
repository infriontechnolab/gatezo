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
        <div class="mt-6 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-neutral-700">New registrations are closed. <b>Already registered?</b> Get your entry pass below.</div>
        <form method="post" action="{{ route('event.find', $event) }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span class="text-sm font-medium">Your name</span>
                <input name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="The name you registered with" class="mt-1 w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-[var(--accent)] focus:outline-none">
                @error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block">
                <span class="text-sm font-medium">Phone or email you registered with</span>
                <input name="contact" value="{{ old('contact') }}" required autocomplete="off" placeholder="98240 17351 or you@example.com" class="mt-1 w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-[var(--accent)] focus:outline-none">
                @error('contact')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            @if ($event->ask_marketing_opt_in)
                <label class="flex items-start gap-3 text-sm text-neutral-700">
                    <input name="marketing_opt_in" value="1" type="checkbox" @checked(old('marketing_opt_in')) class="mt-0.5 size-5 shrink-0 accent-[var(--accent)]">
                    <span>Send me WhatsApp updates about upcoming events from these organizers. <span class="text-neutral-400">Reply STOP any time.</span></span>
                </label>
            @endif
            <button class="w-full rounded-xl py-3.5 text-base font-semibold text-white" style="background: var(--accent)">Get my pass</button>
            <p class="text-center text-xs text-neutral-400">No app, no account.</p>
        </form>
    @else
        <form method="post" action="{{ route('event.register', $event) }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span class="text-sm font-medium">Your name</span>
                <input name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="Full name" class="mt-1 w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-[var(--accent)] focus:outline-none">
                @error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block">
                <span class="text-sm font-medium">Phone <span class="text-neutral-400">(so you can find this pass again)</span></span>
                <input name="phone" value="{{ old('phone') }}" type="tel" inputmode="tel" autocomplete="tel" placeholder="10-digit mobile" pattern="[0-9+()\s.-]{8,25}" title="Digits only, 10 for India or with country code" class="mt-1 w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-[var(--accent)] focus:outline-none">
                @error('phone')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            @if ($event->ask_email)
                <label class="block">
                    <span class="text-sm font-medium">Email <span class="text-neutral-400">(optional)</span></span>
                    <input name="email" value="{{ old('email') }}" type="email" inputmode="email" autocomplete="email" placeholder="you@example.com" class="mt-1 w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-[var(--accent)] focus:outline-none">
                    @error('email')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
            @endif
            @if ($event->ask_marketing_opt_in)
                <label class="flex items-start gap-3 text-sm text-neutral-700">
                    <input name="marketing_opt_in" value="1" type="checkbox" @checked(old('marketing_opt_in')) class="mt-0.5 size-5 shrink-0 accent-[var(--accent)]">
                    <span>Send me WhatsApp updates about upcoming events from these organizers. <span class="text-neutral-400">Reply STOP any time.</span></span>
                </label>
            @endif
            <button class="w-full rounded-xl py-3.5 text-base font-semibold text-white" style="background: var(--accent)">Get my pass</button>
            <p class="text-center text-xs text-neutral-400">Free event. No app, no account. Your details go to the organizers of this event. <a href="{{ route('privacy') }}" class="underline">Privacy</a></p>
            <p class="rounded-xl bg-neutral-100 px-4 py-3 text-center text-sm text-neutral-600"><b>Already registered?</b> Enter the same name and {{ $event->ask_email ? 'phone or email' : 'phone' }} and we'll show your existing pass.</p>
        </form>
    @endunless
@endsection
