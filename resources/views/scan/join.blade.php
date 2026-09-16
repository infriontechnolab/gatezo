@extends('layouts.public', ['title' => 'Volunteer scanner'])
@section('content')
    <h1 class="text-2xl font-bold">Volunteer scanner</h1>
    <p class="mt-1 text-neutral-500">{{ session('hint', 'Ask the organizer for the 6-digit event code.') }}</p>
    <form method="post" action="{{ route('scan.join.post') }}" class="mt-6 space-y-4">
        @csrf
        <label class="block">
            <span class="text-sm font-medium">Event code</span>
            <input name="code" value="{{ old('code') }}" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus class="mt-1 w-full rounded-xl border border-neutral-300 px-4 py-3 text-center font-mono text-2xl tracking-[0.4em] focus:border-neutral-900 focus:outline-none">
            @error('code')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block">
            <span class="text-sm font-medium">Your name</span>
            <input name="name" value="{{ old('name') }}" required autocomplete="name" class="mt-1 w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-neutral-900 focus:outline-none">
            @error('name')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
        </label>
        <button class="w-full rounded-xl bg-neutral-900 py-3.5 text-base font-semibold text-white">Open scanner</button>
    </form>
@endsection
