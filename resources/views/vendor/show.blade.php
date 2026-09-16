@extends('layouts.public', ['title' => 'Vendor · '.$stall->name])
@push('head')
    @vite('resources/js/scanner.js')
@endpush
@section('content')
<div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-neutral-200">
    <div class="text-xs font-medium uppercase tracking-wider text-neutral-400">Your stall</div>
    <h1 class="text-xl font-bold">{{ $stall->name }}</h1>
    <div class="mt-3 grid grid-cols-2 gap-3 text-center">
        <div class="rounded-xl bg-neutral-50 p-3"><div class="text-2xl font-bold">{{ $stall->view_count }}</div><div class="text-xs text-neutral-500">menu scans</div></div>
        <div class="rounded-xl bg-neutral-50 p-3"><div class="text-2xl font-bold" x-data x-text="$store.leadCount ?? {{ $leads->count() }}">{{ $leads->count() }}</div><div class="text-xs text-neutral-500">leads</div></div>
    </div>
</div>

{{-- Lead capture: same scanner component in "lead" mode --}}
<div x-data="scanner({ mode: 'lead', bundleUrl: @js($bundleUrl), leadUrl: @js($leadUrl), eventSlug: @js('vendor-'.$stall->public_code) })" x-init="init()" class="mt-4 overflow-hidden rounded-2xl bg-neutral-950 text-white">
    <div class="flex items-center justify-between px-4 py-2 text-xs text-neutral-400">
        <span>Scan an attendee's pass to save them as a lead</span>
        <span x-text="online ? 'online' : 'offline'"></span>
    </div>
    <div class="relative aspect-square w-full bg-black">
        <video x-ref="video" playsinline muted class="h-full w-full object-cover"></video>
        <canvas x-ref="canvas" class="hidden"></canvas>
        <div class="pointer-events-none absolute inset-8 rounded-2xl border-2 border-white/40"></div>
        <div x-show="flash" x-transition.opacity.duration.150ms class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center"
             :class="{ 'bg-emerald-600/95': flash?.kind === 'ok', 'bg-amber-500/95': flash?.kind === 'warn', 'bg-red-600/95': flash?.kind === 'bad' }">
            <div class="text-5xl" x-text="flash?.kind === 'ok' ? '✓' : (flash?.kind === 'warn' ? '!' : '✕')"></div>
            <div class="mt-2 text-2xl font-bold" x-text="flash?.title"></div>
            <div class="mt-1 text-lg opacity-90" x-text="flash?.detail"></div>
        </div>
        <div x-show="cameraError" class="absolute inset-0 flex items-center justify-center bg-neutral-900 p-6 text-center text-sm text-neutral-300" x-text="cameraError"></div>
    </div>
    <form @submit.prevent="manual()" class="flex gap-2 p-3">
        <input x-model="manualCode" placeholder="Or type pass code" autocapitalize="characters" class="flex-1 rounded-lg bg-neutral-800 px-3 py-2 font-mono uppercase tracking-widest">
        <button class="rounded-lg bg-neutral-700 px-4 text-sm font-semibold">Save</button>
    </form>
    <ul class="space-y-1 px-3 pb-3 text-sm">
        <template x-for="r in recent" :key="r.client_id">
            <li class="flex justify-between rounded-lg bg-neutral-900 px-3 py-2"><span x-text="r.name"></span><span class="text-xs" :class="r.status === 'ok' ? 'text-emerald-400' : 'text-amber-400'" x-text="r.status"></span></li>
        </template>
    </ul>
</div>
<p class="mt-2 text-xs text-neutral-500">Only attendees who switched on "allow stalls to contact me" on their pass can be saved. Ask them to flip it on their phone if you see "not opted in".</p>

<div class="mt-6 flex items-center justify-between">
    <h2 class="font-semibold">Leads</h2>
    <a href="{{ $csvUrl }}" class="text-sm underline">Download CSV</a>
</div>
<ul class="mt-2 divide-y divide-neutral-200 rounded-2xl bg-white ring-1 ring-neutral-200">
    @forelse ($leads as $lead)
        <li class="flex items-center justify-between px-4 py-3">
            <div><div class="font-medium">{{ $lead->attendee->name }}</div><div class="text-sm text-neutral-500">{{ $lead->attendee->phone }} {{ $lead->attendee->email }}</div></div>
            <div class="text-xs text-neutral-400">{{ $lead->created_at->format('g:i A') }}</div>
        </li>
    @empty
        <li class="px-4 py-6 text-center text-sm text-neutral-400">No leads yet.</li>
    @endforelse
</ul>
<p class="mt-4 text-xs text-neutral-400">This link is private to your stall. Don't share it.</p>
@endsection
