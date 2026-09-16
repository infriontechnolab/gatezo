@extends('layouts.public', ['title' => 'Scanner · '.$event->name])
@push('head')
    @vite('resources/js/scanner.js')
@endpush
@section('content')
{{-- Everything below is driven by resources/js/scanner.js (Alpine component "scanner").
     It must keep working with no network: bundle + queue live in IndexedDB. --}}
<div x-data="scanner({ mode: 'gate', bundleUrl: @js(route('scan.bundle')), syncUrl: @js(route('scan.sync')), dutyUrl: @js(route('scan.duty')), gateSignPrefix: @js(url('/scan/g/'.$event->slug.'/')), eventSlug: @js($event->slug), duty: @js(session('duty')) })"
     x-init="init()" class="-mx-5 -mt-8 flex min-h-dvh flex-col bg-neutral-950 text-white">

    {{-- Top bar --}}
    <div class="flex items-center justify-between px-4 py-3 text-sm">
        <div class="truncate font-semibold">{{ $event->name }}</div>
        <div class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full" :class="online ? 'bg-emerald-400' : 'bg-amber-400'"></span>
            <span x-text="online ? 'online' : 'offline'" class="text-neutral-400"></span>
            <span class="text-neutral-500" x-show="pending > 0" x-text="pending + ' queued'"></span>
        </div>
    </div>

    {{-- Gate + direction --}}
    <div class="flex gap-2 px-4 pb-3">
        <select x-model.number="gateId" class="flex-1 rounded-lg bg-neutral-800 px-3 py-2 text-sm">
            <option value="">No gate</option>
            <template x-for="g in gates.filter(g => g.is_entry)" :key="g.id"><option :value="g.id" x-text="g.name + ' (' + g.code + ')'"></option></template>
        </select>
        <button x-show="bundle?.event?.allow_reentry" @click="direction = direction === 'in' ? 'out' : 'in'"
                class="rounded-lg px-4 py-2 text-sm font-semibold" :class="direction === 'in' ? 'bg-emerald-600' : 'bg-neutral-600'" x-text="direction.toUpperCase()"></button>
    </div>

    {{-- Camera --}}
    <div class="relative aspect-square w-full overflow-hidden bg-black">
        <video x-ref="video" playsinline muted class="h-full w-full object-cover"></video>
        <canvas x-ref="canvas" class="hidden"></canvas>
        <div class="pointer-events-none absolute inset-8 rounded-2xl border-2 border-white/40"></div>
        {{-- Flash overlay --}}
        <div x-show="flash" x-transition.opacity.duration.150ms class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center"
             :class="{ 'bg-emerald-600/95': flash?.kind === 'ok', 'bg-amber-500/95': flash?.kind === 'warn', 'bg-red-600/95': flash?.kind === 'bad' }">
            <div class="text-5xl" x-text="flash?.kind === 'ok' ? '✓' : (flash?.kind === 'warn' ? '!' : '✕')"></div>
            <div class="mt-2 text-2xl font-bold" x-text="flash?.title"></div>
            <div class="mt-1 text-lg opacity-90" x-text="flash?.detail"></div>
        </div>
        <div x-show="cameraError" class="absolute inset-0 flex items-center justify-center bg-neutral-900 p-6 text-center text-sm text-neutral-300" x-text="cameraError"></div>
    </div>

    {{-- Manual fallback --}}
    <form @submit.prevent="manual()" class="flex gap-2 px-4 py-3">
        <input x-model="manualCode" placeholder="Type pass code" autocapitalize="characters" class="flex-1 rounded-lg bg-neutral-800 px-3 py-2 font-mono uppercase tracking-widest">
        <button class="rounded-lg bg-neutral-700 px-4 text-sm font-semibold">Check in</button>
    </form>

    {{-- Recent --}}
    <ul class="flex-1 space-y-1 overflow-y-auto px-4 pb-4 text-sm">
        <template x-for="r in recent" :key="r.client_id">
            <li class="flex items-center justify-between rounded-lg bg-neutral-900 px-3 py-2">
                <span><span x-text="r.name" class="font-medium"></span> <span class="text-neutral-500" x-text="r.code"></span></span>
                <span class="text-xs" :class="{ 'text-emerald-400': r.status === 'ok', 'text-amber-400': r.status === 'duplicate', 'text-red-400': ['invalid_signature','unknown_pass','revoked'].includes(r.status), 'text-neutral-500': r.status === 'queued' }" x-text="r.status"></span>
            </li>
        </template>
    </ul>

    <div class="flex items-center justify-between px-4 py-3 text-xs text-neutral-500">
        <span>{{ $volunteer->name }} · <span x-text="bundle ? bundle.passes.length + ' passes cached' : 'no bundle yet'"></span></span>
        <form method="post" action="{{ route('scan.leave') }}">@csrf<button class="underline">Leave</button></form>
    </div>
</div>
@endsection
