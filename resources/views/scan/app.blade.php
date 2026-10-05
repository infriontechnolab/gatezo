@extends('layouts.public', ['title' => 'Scanner · '.$event->name, 'hideHeader' => true])
@push('head')
    @vite('resources/js/scanner.js')
@endpush
@section('content')
{{-- Everything below is driven by resources/js/scanner.js (Alpine component "scanner").
     It must keep working with no network: bundle + queue live in IndexedDB. --}}
<div x-data="scanner({ mode: 'gate', bundleUrl: @js(route('scan.bundle')), syncUrl: @js(route('scan.sync')), dutyUrl: @js(route('scan.duty')), claimUrl: @js(route('scan.claim')), walkupUrl: @js(route('scan.walkup')), gateSignPath: @js('/scan/g/'.$event->slug.'/'), eventSlug: @js($event->slug), duty: @js(session('duty')), shift: @js($shift ? ['gate_id' => $shift->gate_id, 'gate_name' => $shift->gate?->name, 'label' => $shift->label, 'from' => $shift->starts_at?->format('g:i A'), 'to' => $shift->ends_at?->format('g:i A')] : null) })"
     class="-mx-5 -mt-8 flex min-h-dvh flex-col bg-neutral-950 text-white">

    {{-- Top bar --}}
    <div class="flex items-center justify-between px-4 py-3 text-sm">
        <div class="truncate font-semibold">{{ $event->name }}</div>
        <div class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full" :class="online ? 'bg-emerald-400' : 'bg-amber-400'"></span>
            <span x-text="online ? 'online' : 'offline'" class="text-neutral-400"></span>
            <span class="text-neutral-500" x-show="pending > 0" x-text="pending + ' queued'"></span>
        </div>
    </div>

    {{-- Roster: this volunteer's current/next shift --}}
    @if ($shift)
        <div class="mx-4 mb-3 rounded-lg bg-neutral-800 px-4 py-2 text-sm">
            <span class="text-neutral-400">Your shift:</span>
            <span class="font-semibold">{{ $shift->gate?->name ?? $shift->label ?? 'Anywhere' }}</span>
            @if ($shift->starts_at)<span class="text-neutral-400">· {{ $shift->starts_at->format('g:i A') }}@if ($shift->ends_at) to {{ $shift->ends_at->format('g:i A') }}@endif</span>@endif
            @if ($shift->label && $shift->gate)<span class="text-neutral-400">· {{ $shift->label }}</span>@endif
        </div>
    @endif

    <div x-show="storageWarning" x-cloak class="mx-4 mb-3 rounded-lg bg-neutral-800 px-4 py-2 text-xs text-amber-300">This browser can't store scans on the phone. Scanning works, but don't close this tab until the queue shows 0.</div>

    {{-- Session expired: scans are safe in the queue, volunteer just needs to rejoin --}}
    <a x-show="sessionExpired" x-cloak href="{{ route('scan.join') }}" class="mx-4 mb-3 block rounded-lg bg-amber-500 px-4 py-3 text-sm font-semibold text-neutral-950">
        Your session expired. Tap to rejoin with the event code. <span class="font-normal" x-show="pending > 0" x-text="'(' + pending + ' scans are saved and will sync after)'"></span>
    </a>

    {{-- Capacity: warns, never blocks. As fresh as the last bundle refresh plus this phone's own scans. --}}
    <div x-show="crowdLevel" x-cloak class="mx-4 mb-3 rounded-lg px-4 py-2 text-sm font-semibold" :class="crowdLevel === 'full' ? 'bg-red-600 text-white' : 'bg-amber-500 text-neutral-950'">
        <span x-text="crowdLevel === 'full' ? 'At capacity' : 'Nearly full'"></span>
        <span class="font-normal" x-text="'· about ' + bundle?.inside?.toLocaleString() + ' of ' + bundle?.event?.capacity?.toLocaleString() + ' inside' + (crowdLevel === 'full' ? '. Check with the organizer before letting more in.' : '')"></span>
    </div>

    {{-- Gate + direction --}}
    <div class="flex gap-2 px-4 pb-3">
        <select x-model.number="gateId" class="flex-1 rounded-lg bg-neutral-800 px-3 py-2 text-sm">
            <option value="">No gate</option>
            <template x-for="g in gates.filter(g => g.is_entry || (g.is_goodies && bundle?.goodies))" :key="g.id"><option :value="g.id" x-text="g.name + ' (' + g.code + ')' + (g.is_goodies ? ' · ' + bundle.goodies.name : '')"></option></template>
        </select>
        {{-- Goodies counter: no direction, just what's left (approximate while offline) --}}
        <div x-show="goodiesMode" x-cloak class="rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold" x-text="bundle?.goodies?.left == null ? bundle?.goodies?.name : bundle.goodies.left + ' left'"></div>
        <button x-show="bundle?.event?.allow_reentry && !goodiesMode" @click="direction = direction === 'in' ? 'out' : 'in'"
                class="rounded-lg px-4 py-2 text-sm font-semibold" :class="direction === 'in' ? 'bg-emerald-600' : 'bg-neutral-600'" x-text="direction.toUpperCase()"></button>
    </div>

    {{-- Camera --}}
    <div class="relative aspect-square w-full overflow-hidden bg-black">
        <video x-ref="video" playsinline muted class="h-full w-full object-cover"></video>
        <canvas x-ref="canvas" class="hidden"></canvas>
        <div class="pointer-events-none absolute inset-8 rounded-2xl border-2 border-white/40"></div>
        {{-- Draw winner: verify + claim --}}
        <div x-show="winner" x-cloak class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-3 bg-amber-400 px-4 py-3 text-neutral-950">
            <div><div class="text-[10px] font-bold uppercase tracking-widest">Draw winner</div><div class="font-semibold" x-text="winner?.name + ' · ' + winner?.prize"></div></div>
            <button @click="claimWinner()" class="rounded-lg bg-neutral-950 px-4 py-2 text-sm font-semibold text-white">Mark claimed</button>
        </div>
        {{-- Already inside / already collected: the volunteer decides. Stays up until they tap. --}}
        <div x-show="hold" x-cloak class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-amber-500 p-5 text-center text-neutral-950">
            <div class="text-[11px] font-bold uppercase tracking-widest" x-text="hold?.title"></div>
            <div class="mt-1 text-2xl font-bold leading-tight" x-text="hold?.name"></div>
            <div class="mt-1 text-sm" x-text="hold?.detail"></div>
            <div x-show="hold?.kind !== 'goodies'" class="mt-5 grid w-full grid-cols-2 gap-3">
                <button @click="decide('{{ \App\Enums\CheckinDecision::TurnedAway->value }}')" class="rounded-xl bg-neutral-950 px-4 py-4 text-base font-bold text-white">Turn away</button>
                <button @click="decide('{{ \App\Enums\CheckinDecision::LetIn->value }}')" class="rounded-xl bg-white px-4 py-4 text-base font-bold text-neutral-950">Let in anyway</button>
            </div>
            <div x-show="hold?.kind === 'goodies'" class="mt-5 grid w-full grid-cols-2 gap-3">
                <button @click="decide('{{ \App\Enums\HandoutDecision::Refused->value }}')" class="rounded-xl bg-neutral-950 px-4 py-4 text-base font-bold text-white">Don't give</button>
                <button @click="decide('{{ \App\Enums\HandoutDecision::GaveAnyway->value }}')" class="rounded-xl bg-white px-4 py-4 text-base font-bold text-neutral-950">Give anyway</button>
            </div>
        </div>
        {{-- Flash overlay --}}
        <div x-show="flash" x-transition.opacity.duration.150ms class="absolute inset-0 z-10 flex flex-col items-center justify-center p-6 text-center"
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
        <button class="rounded-lg bg-neutral-700 px-4 text-sm font-semibold" x-text="goodiesMode ? 'Give' : 'Check in'">Check in</button>
    </form>

    {{-- No pass on them (only a ticket from another system, or a flat phone): find them in the cached list --}}
    <div class="px-4 pb-3">
        <button type="button" @click="search = { q: '' }" class="w-full rounded-lg border border-neutral-700 px-4 py-2 text-sm font-semibold text-neutral-200">Find by name</button>
    </div>
    <template x-if="search">
        <div class="fixed inset-0 z-20 flex flex-col bg-neutral-950 p-4" @keydown.escape.window="search = null">
            <div class="flex items-center gap-2">
                <input x-model="search.q" x-init="$nextTick(() => $el.focus())" autocomplete="off" placeholder="Type part of their name" class="flex-1 rounded-lg bg-neutral-800 px-3 py-3 text-base">
                <button type="button" @click="search = null" class="rounded-lg bg-neutral-800 px-4 py-3 text-sm font-semibold">Close</button>
            </div>
            <p class="mt-2 text-xs text-neutral-400">Ask them to confirm the detail shown before you let them in.</p>
            <ul class="mt-3 flex-1 space-y-2 overflow-y-auto">
                <template x-for="p in searchResults" :key="p.code">
                    <li class="flex items-center justify-between gap-3 rounded-lg bg-neutral-900 px-3 py-3">
                        <div class="min-w-0">
                            <div class="truncate font-semibold"><span x-text="p.name"></span> <span x-show="p.is_vip" class="text-xs text-amber-400">VIP</span></div>
                            <div class="text-sm text-neutral-300" x-text="p.hint ?? 'No phone or email on file: check their ticket or ID'"></div>
                            <div class="text-xs text-neutral-500"><span class="font-mono" x-text="p.code"></span><span x-show="p.inside"> · already inside</span></div>
                        </div>
                        <button type="button" @click="pickFromSearch(p.code)" class="shrink-0 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold" x-text="goodiesMode ? 'Give' : 'Check in'"></button>
                    </li>
                </template>
                <li x-show="(search.q ?? '').trim().length >= 2 && !searchResults.length" class="px-1 text-sm text-neutral-400">No one by that name in the downloaded list.</li>
            </ul>
        </div>
    </template>

    {{-- Walk-up: register someone without a pass and check them in (needs signal) --}}
    @if ($event->allow_self_register)
        <div x-show="!goodiesMode && direction === 'in'" class="px-4 pb-3">
            <button type="button" @click="walkup = { name: '', phone: '', optIn: false, busy: false, error: '' }" class="w-full rounded-lg border border-neutral-700 px-4 py-2 text-sm font-semibold text-neutral-200">+ Walk-up without a pass</button>
        </div>
        <template x-if="walkup">
            <div class="fixed inset-0 z-20 flex items-end bg-black/70" @keydown.escape.window="walkup = null">
                <form @submit.prevent="submitWalkup()" class="w-full space-y-3 rounded-t-2xl bg-neutral-900 p-5">
                    <div class="text-lg font-bold">Walk-up</div>
                    <p class="text-sm text-neutral-400">Registers them and checks them in here. Needs signal.</p>
                    <input x-model="walkup.name" required maxlength="120" autocomplete="off" placeholder="Name" class="w-full rounded-lg bg-neutral-800 px-3 py-3 text-base">
                    <input x-model="walkup.phone" type="tel" inputmode="tel" maxlength="25" autocomplete="off" placeholder="Phone (optional, finds an existing pass)" class="w-full rounded-lg bg-neutral-800 px-3 py-3 text-base">
                    @if ($event->ask_marketing_opt_in)
                        <label class="flex items-start gap-3 rounded-lg bg-neutral-800 px-3 py-3 text-sm text-neutral-200">
                            <input x-model="walkup.optIn" type="checkbox" class="mt-0.5 size-5 shrink-0 accent-emerald-500">
                            <span>Ask them: <b>WhatsApp updates about upcoming events?</b> Tick only if they say yes.</span>
                        </label>
                    @endif
                    <p x-show="walkup.error" class="text-sm text-red-400" x-text="walkup.error"></p>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="walkup = null" class="rounded-xl bg-neutral-800 px-4 py-3 font-semibold">Cancel</button>
                        <button :disabled="walkup.busy" class="rounded-xl bg-emerald-600 px-4 py-3 font-bold disabled:opacity-50" x-text="walkup.busy ? 'Checking in…' : 'Check in'"></button>
                    </div>
                </form>
            </div>
        </template>
        <template x-if="walkupDone">
            <div class="fixed inset-0 z-20 flex flex-col items-center justify-center bg-neutral-950 p-6 text-center">
                <div class="text-[11px] font-bold uppercase tracking-widest" :class="walkupDone.status === 'ok' ? 'text-emerald-400' : 'text-amber-400'"
                     x-text="walkupDone.status === 'ok' ? (walkupDone.existing ? 'Had a pass · checked in' : 'Registered · checked in') : 'Already inside · flagged'"></div>
                <div class="mt-1 text-2xl font-bold" x-text="walkupDone.name"></div>
                <div class="mt-5 w-56 rounded-xl bg-white p-3 [&_svg]:h-auto [&_svg]:w-full" x-html="walkupDone.qr"></div>
                <p class="mt-3 text-sm text-neutral-300">Ask them to scan this with their phone camera to keep their pass for re-entry.</p>
                <button type="button" @click="walkupDone = null" class="mt-6 w-full rounded-xl bg-white px-4 py-4 text-base font-bold text-neutral-950">Done</button>
            </div>
        </template>
    @endif

    {{-- Recent --}}
    <ul class="flex-1 space-y-1 overflow-y-auto px-4 pb-4 text-sm">
        <template x-for="r in recent" :key="r.client_id">
            <li class="flex items-center justify-between rounded-lg bg-neutral-900 px-3 py-2">
                <span><span x-text="r.name" class="font-medium"></span> <span class="text-neutral-500" x-text="r.code"></span></span>
                <span class="text-xs" :class="{ 'text-emerald-400': r.status === 'ok', 'text-amber-400': ['duplicate','already_collected','not_checked_in','ticket_type'].includes(r.status), 'text-red-400': ['invalid_signature','unknown_pass','revoked','turned_away','refused','static_pass','expired_pass'].includes(r.status), 'text-neutral-500': r.status === 'queued' }" x-text="r.status.replaceAll('_', ' ')"></span>
            </li>
        </template>
    </ul>

    <div class="flex items-center justify-between px-4 py-3 text-xs text-neutral-500">
        <span>{{ $volunteer->name }} · <span x-text="bundle ? bundle.passes.length + ' passes cached' : 'no bundle yet'"></span></span>
        <form method="post" action="{{ route('scan.leave') }}">@csrf<button class="underline">Leave</button></form>
    </div>
</div>
@endsection
