<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->name }} · Gate board</title>
    <style>:root { --accent: {{ $event->accent_hex }}; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js']) {{-- app.js is a module (deferred), so the alpine:init listener below registers first --}}
    <style>
        /* Tablet on a stand, 2–3 m away: one number you can read, everything else secondary. */
        .num { font-variant-numeric: tabular-nums; letter-spacing: -0.04em; }
        .lvl-ok { --bar: #4A9B5E; } .lvl-near { --bar: #D9713C; } .lvl-full { --bar: #B23A48; } .lvl-none { --bar: var(--accent); }
    </style>
</head>
<body class="min-h-dvh bg-neutral-950 text-white antialiased" x-data="board(@js(\Illuminate\Support\Facades\URL::signedRoute('board.json', $event)), @js($stats))" x-init="init()">
    <main class="mx-auto flex min-h-dvh max-w-5xl flex-col px-8 py-8" :class="'lvl-' + s.level">
        <header class="flex items-center justify-between text-neutral-400">
            <div class="flex items-center gap-3">
                <span class="h-3 w-3 rounded-full" style="background: var(--accent)"></span>
                <span class="text-lg font-semibold text-white">{{ $event->name }}</span>
                @if ($event->venue)<span class="hidden sm:inline">· {{ $event->venue }}</span>@endif
            </div>
            <div class="text-sm"><span x-text="online ? 'Live' : 'Reconnecting…'"></span> · <span x-text="s.as_of"></span></div>
        </header>

        <section class="my-auto py-10 text-center">
            <div class="text-sm font-semibold uppercase tracking-[0.25em] text-neutral-400">Inside now</div>
            <div class="num mt-2 text-[9rem] font-extrabold leading-none sm:text-[13rem]" x-text="s.inside.toLocaleString()">{{ number_format($stats['inside']) }}</div>
            <template x-if="s.capacity">
                <div class="mx-auto mt-6 w-full max-w-2xl">
                    <div class="h-4 overflow-hidden rounded-full bg-neutral-800">
                        <div class="h-full rounded-full transition-all duration-700" :style="'width:' + Math.min(100, s.pct) + '%; background: var(--bar)'"></div>
                    </div>
                    <div class="mt-3 flex justify-between text-lg text-neutral-400">
                        <span><span x-text="s.pct"></span>% of <span x-text="s.capacity.toLocaleString()"></span></span>
                        <span x-show="s.level === 'full'" class="font-semibold text-red-400">At capacity</span>
                        <span x-show="s.level === 'near'" class="font-semibold text-orange-400">Nearly full</span>
                    </div>
                </div>
            </template>
        </section>

        <section class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl bg-neutral-900 p-5">
                <div class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Checked in</div>
                <div class="num mt-1 text-4xl font-bold" x-text="s.checked_in.toLocaleString()"></div>
                <div class="text-sm text-neutral-500"><span x-text="s.registered.toLocaleString()"></span> registered</div>
            </div>
            <div class="rounded-2xl bg-neutral-900 p-5">
                <div class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Last 15 minutes</div>
                <div class="num mt-1 text-4xl font-bold" x-text="s.last_15.toLocaleString()"></div>
                <div class="text-sm text-neutral-500">arrivals</div>
            </div>
            <div class="rounded-2xl bg-neutral-900 p-5">
                <div class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Gates</div>
                <ul class="mt-1 space-y-1">
                    <template x-for="g in s.gates" :key="g.code">
                        <li class="flex items-center justify-between text-sm">
                            <span><span class="font-mono text-neutral-500" x-text="g.code"></span> <span x-text="g.name"></span></span>
                            <span class="num"><span x-text="g.ins"></span> <span class="text-neutral-500" x-show="g.recent" x-text="'(+' + g.recent + ')'"></span></span>
                        </li>
                    </template>
                </ul>
            </div>
        </section>

        <footer class="mt-6 text-center text-xs text-neutral-600">Gatezo · refreshes every 5 seconds · no login needed on this screen</footer>
    </main>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('board', (url, initial) => ({
                s: initial, online: true,
                init() { setInterval(() => this.refresh(), 5000); },
                async refresh() {
                    try {
                        const r = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (!r.ok) throw new Error(r.status);
                        this.s = await r.json(); this.online = true;
                    } catch { this.online = false; }
                },
            }));
        });
    </script>
</body>
</html>
