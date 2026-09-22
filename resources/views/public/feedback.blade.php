@extends('layouts.public')
@section('content')
    <h1 class="text-2xl font-bold">How was it?</h1>
    <p class="mt-1 text-neutral-500">Takes 10 seconds. Anonymous unless you came from your pass.</p>
    <form method="post" action="{{ route('event.feedback', $event) }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="pass_code" value="{{ request('pass') }}">
        <fieldset>
            <legend class="text-sm font-medium">Overall</legend>
            <div class="mt-2 flex justify-between gap-2" x-data="{ r: {{ old('rating', 0) }} }">
                @for ($i = 1; $i <= 5; $i++)
                    <label class="flex-1">
                        <input type="radio" name="rating" value="{{ $i }}" class="peer sr-only" x-model="r" required>
                        <span class="block cursor-pointer rounded-xl border border-neutral-300 bg-white py-3 text-center text-2xl peer-checked:border-[var(--accent)] peer-checked:bg-[var(--accent)] peer-checked:text-white">{{ $i }}</span>
                    </label>
                @endfor
            </div>
            @error('rating')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
        </fieldset>
        <label class="block">
            <span class="text-sm font-medium">Anything to add? <span class="text-neutral-400">(optional)</span></span>
            <textarea name="comment" rows="4" placeholder="What was great? What should we fix next time?" class="mt-1 w-full rounded-xl border border-neutral-300 px-4 py-3 text-base focus:border-[var(--accent)] focus:outline-none">{{ old('comment') }}</textarea>
        </label>
        <button class="w-full rounded-xl py-3.5 text-base font-semibold text-white" style="background: var(--accent)">Send</button>
    </form>
@endsection
