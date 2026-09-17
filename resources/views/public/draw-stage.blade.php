<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $draw->name }} · {{ $event->name }}</title>
    <style>:root { --accent: {{ $event->accent_hex }}; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .num { font-variant-numeric: tabular-nums; }
        @keyframes pop { 0% { transform: scale(.6); opacity: 0 } 60% { transform: scale(1.06) } 100% { transform: scale(1); opacity: 1 } }
        .pop { animation: pop .6s cubic-bezier(.2,.9,.3,1.2) both; }
        .confetti i { position: absolute; top: -10px; width: 10px; height: 16px; opacity: .9; animation: fall linear forwards; }
        @keyframes fall { to { transform: translateY(110vh) rotate(720deg); opacity: .2 } }
    </style>
</head>
<body class="min-h-dvh overflow-hidden bg-neutral-950 text-white antialiased"
      x-data="stage(@js($jsonUrl), @js($state))" x-init="init()">
    <div class="confetti pointer-events-none fixed inset-0" x-ref="confetti"></div>
    <main class="mx-auto flex min-h-dvh max-w-6xl flex-col px-8 py-8">
        <header class="flex items-center justify-between text-neutral-400">
            <div class="flex items-center gap-3">
                <span class="h-3 w-3 rounded-full" style="background: var(--accent)"></span>
                <span class="text-lg font-semibold text-white">{{ $event->name }}</span>
                <span>· {{ $draw->name }}</span>
            </div>
            <div class="text-sm"><span x-text="s.pool_size.toLocaleString()"></span> in the pool · <span x-text="online ? 'live' : 'reconnecting…'"></span></div>
        </header>

        {{-- Center stage --}}
        <section class="my-auto py-10 text-center">
            {{-- Waiting --}}
            <template x-if="!s.current && s.status !== 'finished' && !rolling">
                <div>
                    <div class="text-sm font-semibold uppercase tracking-[0.3em] text-neutral-500">Lucky draw</div>
                    <div class="mt-4 text-5xl font-extrabold sm:text-7xl">{{ $draw->name }}</div>
                    <div class="mt-6 text-2xl text-neutral-400" x-show="s.prizes.length">
                        <template x-for="p in s.prizes" :key="p.name"><span class="mx-3 inline-block"><span x-text="p.quantity"></span> × <span x-text="p.name"></span></span></template>
                    </div>
                    <div class="mt-10 text-neutral-500" x-show="s.status === 'draft'">Waiting for the organizer to run the draw…</div>
                </div>
            </template>

            {{-- Rolling names --}}
            <template x-if="rolling">
                <div>
                    <div class="text-sm font-semibold uppercase tracking-[0.3em] text-neutral-500" x-text="pending?.prize"></div>
                    <div class="num mt-6 text-6xl font-extrabold text-neutral-200 sm:text-8xl" x-text="rollName"></div>
                </div>
            </template>

            {{-- Winner --}}
            <template x-if="s.current && !rolling">
                <div class="pop">
                    <div class="text-sm font-semibold uppercase tracking-[0.3em]" style="color: var(--accent)" x-text="s.current.prize + (s.current.rank > 1 ? ' · backup' : '')"></div>
                    <div class="mt-4 text-7xl font-extrabold leading-none sm:text-9xl" x-text="s.current.name"></div>
                    <div class="num mt-4 text-3xl text-neutral-400" x-show="s.current.phone" x-text="s.current.phone"></div>
                    <div class="mt-10 inline-flex items-center gap-3 rounded-full bg-neutral-900 px-6 py-3 text-xl">
                        <span class="text-neutral-400">Come to the stage within</span>
                        <span class="num font-bold" :class="secondsLeft <= 30 ? 'text-red-400' : 'text-white'" x-text="fmt(secondsLeft)"></span>
                    </div>
                </div>
            </template>

            {{-- Finished --}}
            <template x-if="s.status === 'finished' && !s.current && !rolling">
                <div>
                    <div class="text-sm font-semibold uppercase tracking-[0.3em] text-neutral-500">That's all</div>
                    <div class="mt-4 text-5xl font-extrabold">Congratulations to all winners</div>
                    <div class="mt-6 text-neutral-500">Verify this draw: seed <span class="font-mono text-xs" x-text="s.seed"></span></div>
                </div>
            </template>
        </section>

        {{-- Claimed so far --}}
        <section x-show="s.claimed.length" class="rounded-2xl bg-neutral-900 p-5">
            <div class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Winners so far</div>
            <ul class="mt-2 grid gap-x-8 gap-y-1 text-lg sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="w in s.claimed" :key="w.id"><li><span class="text-neutral-500" x-text="w.prize + ':'"></span> <span x-text="w.name"></span></li></template>
            </ul>
        </section>
        <footer class="mt-4 text-center text-xs text-neutral-600">Gatezo · seed hash <span class="font-mono" x-text="s.seed_hash.slice(0, 16) + '…'"></span> committed before the draw</footer>
    </main>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('stage', (url, initial) => ({
            s: initial, online: true, rolling: false, pending: null, rollName: '', secondsLeft: 0, _seen: initial.current?.id ?? null,
            init() {
                setInterval(() => this.refresh(), 2000);
                setInterval(() => { if (this.s.current?.deadline) this.secondsLeft = Math.max(0, Math.round((new Date(this.s.current.deadline) - Date.now()) / 1000)); }, 500);
            },
            fmt(n) { return Math.floor(n / 60) + ':' + String(n % 60).padStart(2, '0'); },
            async refresh() {
                try {
                    const r = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!r.ok) throw new Error(r.status);
                    const next = await r.json(); this.online = true;
                    if (next.current && next.current.id !== this._seen) { this._seen = next.current.id; await this.roll(next); }
                    else this.s = next;
                } catch { this.online = false; }
            },
            // Rolling names for reveal_seconds, then the real winner + confetti.
            roll(next) {
                return new Promise((done) => {
                    const names = next.names.length ? next.names : ['…'];
                    this.pending = next.current; this.rolling = true;
                    const ms = (next.presentation?.reveal_seconds ?? 8) * 1000, t0 = Date.now();
                    const tick = () => {
                        const p = (Date.now() - t0) / ms;
                        this.rollName = names[Math.floor(Math.random() * names.length)];
                        if (p < 1) setTimeout(tick, 40 + p * p * 400); else { this.rolling = false; this.s = next; if (next.presentation?.confetti !== false) this.confetti(); done(); }
                    };
                    tick();
                });
            },
            confetti() {
                const c = this.$refs.confetti; c.innerHTML = '';
                const colors = [getComputedStyle(document.documentElement).getPropertyValue('--accent'), '#F5A623', '#ffffff', '#6b2d5c'];
                for (let i = 0; i < 120; i++) { const e = document.createElement('i'); e.style.left = Math.random() * 100 + 'vw'; e.style.background = colors[i % colors.length]; e.style.animationDuration = 2.5 + Math.random() * 2 + 's'; e.style.animationDelay = Math.random() * .8 + 's'; c.appendChild(e); }
                setTimeout(() => (c.innerHTML = ''), 6000);
            },
        }));
    });
    </script>
</body>
</html>
