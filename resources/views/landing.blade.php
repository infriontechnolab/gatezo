<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gatezo — replace the clipboard with a QR code</title>
    <meta name="description" content="Gatezo turns any gate, poster or stall at a local event into a live data point. Attendees scan with their camera, no app. Works when the venue Wi-Fi dies.">
    <meta name="theme-color" content="#E8604C">
    <link rel="icon" href="/icons/icon-192.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,700;12..96,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --coral: #E8604C; --plum: #6B2D5C; --plum-deep: #4A1F3F; --ink: #1C1917; --stone: #FAFAF9; --marigold: #F5A623; }
        html { scroll-behavior: smooth; }
        body { font-family: "Bricolage Grotesque", ui-sans-serif, system-ui, sans-serif; font-optical-sizing: auto; }
        .display { font-weight: 800; letter-spacing: -0.035em; line-height: 0.98; }
        .h2 { font-weight: 700; letter-spacing: -0.025em; line-height: 1.05; }
        .sheet { background: #fff; border: 1.5px dashed #1C1917; border-radius: 10px; }
        .sheet .qr svg { width: 100%; height: auto; }
        .stripe { height: 8px; border-radius: 4px; background: var(--coral); }
        .stripe.volunteer { background: repeating-linear-gradient(45deg, #1C1917 0 6px, #fff 6px 12px); }
        .phone { border-radius: 2rem; background: #0a0a0a; box-shadow: 0 30px 60px -20px rgba(28,25,23,.45), inset 0 0 0 2px #333; }
        .frame { border-radius: 14px; box-shadow: 0 40px 80px -30px rgba(107,45,92,.35), 0 0 0 1px rgba(28,25,23,.08); overflow: hidden; background: #fff; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; border-radius: 999px; padding: .95rem 1.5rem; font-weight: 700; font-size: 1.0625rem; transition: transform .12s ease, background .12s ease; }
        .btn:active { transform: translateY(1px); }
        .btn-coral { background: var(--coral); color: #fff; } .btn-coral:hover { background: #d9503c; }
        .btn-ghost { background: transparent; color: var(--ink); box-shadow: inset 0 0 0 1.5px rgba(28,25,23,.18); } .btn-ghost:hover { background: rgba(28,25,23,.05); }
        .btn-white { background: #fff; color: var(--plum-deep); } .btn-white:hover { background: #fde8e4; }
        :focus-visible { outline: 3px solid var(--marigold); outline-offset: 3px; }
        @keyframes pulse { 0%, 100% { opacity: 1 } 50% { opacity: .35 } }
        .live { animation: pulse 1.6s ease-in-out infinite; }
        @media (prefers-reduced-motion: reduce) { .live { animation: none } html { scroll-behavior: auto } }
    </style>
</head>
<body class="bg-[var(--stone)] text-[var(--ink)] antialiased">

{{-- Nav --}}
<header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
    <a href="/" class="flex items-center gap-2.5" aria-label="Gatezo home">
        <img src="/brand/mark.svg" alt="" class="h-9 w-9">
        <span class="text-xl font-bold tracking-tight">Gatezo</span>
    </a>
    <nav class="hidden items-center gap-8 text-[15px] font-medium text-neutral-600 sm:flex">
        <a href="#how" class="hover:text-[var(--ink)]">How it works</a>
        <a href="#dashboard" class="hover:text-[var(--ink)]">Dashboard</a>
        <a href="#who" class="hover:text-[var(--ink)]">Who it's for</a>
        <a href="/admin/login" class="hover:text-[var(--ink)]">Sign in</a>
    </nav>
    <a href="/admin/register" class="btn btn-coral !px-5 !py-2.5 !text-[15px]">Start your event</a>
</header>

{{-- Hero --}}
<section class="mx-auto grid max-w-6xl items-center gap-14 px-6 pb-20 pt-10 lg:grid-cols-[1.05fr_1fr] lg:pb-28 lg:pt-16">
    <div>
        <h1 class="display text-[2.75rem] sm:text-6xl lg:text-[4.25rem]">Replace the clipboard with a QR code.</h1>
        <p class="mt-6 max-w-xl text-lg leading-relaxed text-neutral-600 sm:text-xl">Print a few sheets, stick them on your gates, posters and stalls, and every scan becomes a live number. Attendees use their phone camera. No app, no account.</p>
        <div class="mt-9 flex flex-wrap items-center gap-3">
            <a href="/admin/register" class="btn btn-coral">Start your event</a>
            <a href="#how" class="btn btn-ghost">See how it works</a>
        </div>
        <p class="mt-5 text-sm text-neutral-500">Free for events under 300 people. Built in Ahmedabad for fairs, fests, tournaments and garba nights.</p>
    </div>

    {{-- Visual: real poster + phone scanner + live count --}}
    <div class="relative mx-auto w-full max-w-md pb-4 lg:max-w-none" aria-hidden="true">
        <div class="sheet mr-auto w-[70%] p-6 text-center shadow-[0_30px_60px_-30px_rgba(28,25,23,.35)]">
            <div class="stripe"></div>
            <div class="mt-5 text-xs font-semibold tracking-wide text-neutral-500">Scan to get your entry pass</div>
            <div class="mt-1 text-2xl font-bold leading-tight">Sharad Utsav Garba</div>
            <div class="qr mx-auto mt-4 w-[68%]">{!! $qr['poster'] !!}</div>
            <div class="mt-4 text-sm text-neutral-500">Free entry · takes 20 seconds</div>
        </div>

        <div class="phone absolute bottom-2 right-0 w-[40%] p-2" x-data="{ n: 0, target: 1099 }" x-init="const t0 = performance.now(); const step = (t) => { const p = Math.min(1, (t - t0) / 1800); n = Math.round(target * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(step); }; requestAnimationFrame(step)">
            <div class="rounded-[1.6rem] bg-neutral-950 p-3 text-white">
                <div class="flex items-center justify-between text-[10px] text-neutral-400"><span>Main Gate</span><span><span class="live inline-block h-1.5 w-1.5 rounded-full bg-[var(--marigold)]"></span> live</span></div>
                <div class="mt-2 rounded-xl bg-emerald-600 px-3 py-4 text-center">
                    <div class="text-2xl leading-none">✓</div>
                    <div class="mt-1 text-sm font-bold">Aarti Shah</div>
                    <div class="text-[10px] opacity-80">VIP · checked in</div>
                </div>
                <div class="mt-3 rounded-xl bg-neutral-900 px-3 py-2.5">
                    <div class="text-[10px] text-neutral-400">Inside now</div>
                    <div class="text-2xl font-extrabold tabular-nums" x-text="n.toLocaleString()">0</div>
                    <div class="mt-1 h-1.5 rounded-full bg-neutral-800"><div class="h-full rounded-full bg-[var(--coral)]" :style="'width:' + Math.round(n / 1500 * 100) + '%'"></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Problem --}}
<section class="bg-[var(--plum)] text-white">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-24">
        <h2 class="h2 max-w-2xl text-3xl sm:text-4xl lg:text-5xl">Running a local event today looks like this.</h2>
        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['A paper list at the gate', 'Or nobody counts at all. By 9 pm no one knows how many people are inside.'],
                ['"Who\'s at Gate 2?"', 'Volunteers get phone calls all night. The organizer runs between gates instead of running the event.'],
                ['Stalls fly blind', 'A stall owner has no idea how many people stopped by, or who to call afterwards.'],
                ['Feedback goes in a box', 'A suggestion box that gets opened a week later, if ever.'],
                ['Lucky draw by chit', 'Names in a bowl, a cousin wins, and half the crowd doesn\'t believe it.'],
                ['After the event: "it went well"', 'No numbers for the committee, the sponsor, or next year\'s planning.'],
            ] as [$title, $body])
                <div class="rounded-2xl bg-white/[0.07] p-6 ring-1 ring-white/10">
                    <div class="text-lg font-bold">{{ $title }}</div>
                    <p class="mt-2 text-[15px] leading-relaxed text-white/75">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- How it works: the printed sheets --}}
<section id="how" class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
    <div class="max-w-2xl">
        <h2 class="h2 text-3xl sm:text-4xl lg:text-5xl">Stick a QR on it. Every scan becomes a data point.</h2>
        <p class="mt-5 text-lg text-neutral-600">Gatezo prints you a kit. Each sheet is a link to a different action. Here are the real sheets, with real codes you can scan right now.</p>
    </div>

    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['poster', false, 'The poster', 'Attendee scans', 'Registers in 20 seconds and gets a pass on their phone. Their own QR, works offline.'],
            ['gate', true, 'The gate sign', 'Volunteer scans', 'Marks themselves on duty at that gate. Then they scan attendee passes: check-in, headcount, re-entry.'],
            ['stall', false, 'The stall card', 'Attendee scans', 'Sees the menu, offers and location. The stall owner sees how many came, and can capture leads if the attendee allows it.'],
            ['exit', false, 'The exit card', 'Attendee scans', 'One tap rating and a comment. Anonymous unless they came from their pass.'],
        ] as [$key, $volunteer, $title, $who, $body])
            <div>
                <div class="sheet p-5">
                    <div class="stripe {{ $volunteer ? 'volunteer' : '' }}"></div>
                    <div class="mt-4 text-sm font-bold">{{ $title }}</div>
                    <div class="qr mx-auto mt-3 w-[70%]">{!! $qr[$key] !!}</div>
                    <div class="mt-3 text-center text-[11px] text-neutral-500">{{ $volunteer ? 'Striped = volunteers only' : 'Any phone camera' }}</div>
                </div>
                <div class="mt-4 px-1">
                    <div class="text-sm font-semibold text-[var(--coral)]">{{ $who }}</div>
                    <p class="mt-1 text-[15px] leading-relaxed text-neutral-600">{{ $body }}</p>
                </div>
            </div>
        @endforeach
    </div>
    <p class="mt-10 max-w-2xl text-neutral-600">One organizer screen sees all of it as it happens. Afterwards, one report to forward to the committee.</p>
</section>

{{-- Dashboard --}}
<section id="dashboard" class="bg-white">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
        <div class="grid gap-10 lg:grid-cols-[1fr_1.6fr] lg:items-start">
            <div class="lg:sticky lg:top-8">
                <h2 class="h2 text-3xl sm:text-4xl lg:text-5xl">The whole event on one screen.</h2>
                <ul class="mt-8 space-y-5 text-[17px] text-neutral-700">
                    <li class="flex gap-3"><span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--coral)]"></span><span><b>Inside now</b>, against capacity, counting people not scans.</span></li>
                    <li class="flex gap-3"><span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--coral)]"></span><span><b>Arrivals per 15 minutes</b>, so you see the peak while it's happening.</span></li>
                    <li class="flex gap-3"><span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--coral)]"></span><span><b>Per gate</b> and <b>who's on duty where</b>, against the roster you planned.</span></li>
                    <li class="flex gap-3"><span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--coral)]"></span><span><b>Feedback</b> as it comes in, with the comments.</span></li>
                    <li class="flex gap-3"><span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[var(--coral)]"></span><span><b>A post-event report</b> and CSV export the next morning.</span></li>
                </ul>
                <p class="mt-8 text-sm text-neutral-500">This is a real screenshot from a demo event, not a mockup.</p>
            </div>
            <div>
                <div class="frame"><img src="/landing/dashboard.png" alt="Gatezo organizer dashboard showing inside-now count, arrivals chart, gates and who's on duty" class="block w-full" loading="lazy"></div>
                <div class="frame mt-6"><img src="/landing/board.png" alt="Gate board: a full-screen live headcount for a tablet at the entrance" class="block w-full" loading="lazy"></div>
                <p class="mt-3 text-sm text-neutral-500">Below: the gate board, a link you open on a tablet at the entrance. No login on that screen.</p>
            </div>
        </div>
    </div>
</section>

{{-- Why different --}}
<section class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
    <h2 class="h2 max-w-2xl text-3xl sm:text-4xl lg:text-5xl">Built for a ground with bad Wi-Fi and a committee with no budget.</h2>
    <div class="mt-12 grid gap-x-12 gap-y-10 sm:grid-cols-2">
        @foreach ([
            ['Nothing to install', 'Attendees scan with the camera they already have. No app, no account, no OTP. If the camera fails, the volunteer types the four-letter code on the pass.'],
            ['Keeps working when the Wi-Fi dies', 'Scanners download the attendee list before the doors open. Every scan is checked on the phone and queued; it syncs when the signal is back. If two gates scan the same pass, it is flagged for you, never blocked at the gate.'],
            ['Pay only for events you run', 'No monthly fee for a society that holds one event a year. Free under 300 attendees; a flat price per event above that.'],
            ['Live, not "refresh and wait"', 'Counts update every few seconds on the dashboard and the gate board. A 3-second lag is fine for a headcount; a stale one is not.'],
            ['A lucky draw people believe', 'The winner is picked from people actually inside, by a seed whose fingerprint is published before the draw. Backups are drawn upfront, so the stage never waits.'],
            ['Print it on any printer', 'The kit is black on white A4. The corner print shop is enough.'],
        ] as [$title, $body])
            <div class="border-t border-neutral-200 pt-6">
                <div class="text-xl font-bold">{{ $title }}</div>
                <p class="mt-2 text-[16px] leading-relaxed text-neutral-600">{{ $body }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Use cases --}}
<section id="who" class="bg-white">
    <div class="mx-auto max-w-6xl px-6 py-20 lg:py-24">
        <h2 class="h2 text-3xl sm:text-4xl">Made for the events that don't have a ticketing budget.</h2>
        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Community fairs & melas', 'Stalls, a food court, a lucky draw and a lot of walk-ups.', 'M3 20L12 4l9 16H3zM12 4v16M7.5 20l2.5-5 2 5M3 20h18'],
                ['College fests', 'Multiple venues, volunteer shifts, sponsors who want numbers.', 'M2 9l10-4 10 4-10 4L2 9zM6 11v4c0 1.5 3 3 6 3s6-1.5 6-3v-4M22 9v6'],
                ['Sports tournaments', 'Per-ground gates, re-entry, and a headcount for the club.', 'M8 3h8v6a4 4 0 01-8 0V3zM8 5H5a3 3 0 003 3M16 5h3a3 3 0 01-3 3M12 13v4M8 21h8M9 17h6'],
                ['Religious gatherings', 'Capacity you can show the police, and prasad counters that know the crowd.', 'M12 3c-1.5 2-2.5 3.5-2.5 5a2.5 2.5 0 005 0c0-1.5-1-3-2.5-5zM6 14h12l-1.5 4h-9L6 14zM4 21h16'],
            ] as [$title, $body, $path])
                <div class="rounded-2xl bg-[var(--stone)] p-6 ring-1 ring-neutral-200/70">
                    <svg class="h-8 w-8 text-[var(--plum)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg>
                    <div class="mt-4 text-lg font-bold">{{ $title }}</div>
                    <p class="mt-1.5 text-[15px] leading-relaxed text-neutral-600">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Final CTA --}}
<section class="bg-[var(--plum-deep)] text-white">
    <div class="mx-auto max-w-6xl px-6 py-20 text-center lg:py-28">
        <h2 class="h2 mx-auto max-w-3xl text-3xl sm:text-4xl lg:text-5xl">Run your next event without the clipboard.</h2>
        <p class="mx-auto mt-5 max-w-xl text-lg text-white/75">Create the event, print the kit, forward one link on WhatsApp. That's the setup.</p>
        <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
            <a href="/admin/register" class="btn btn-white">Start your event</a>
            <a href="https://wa.me/919328964742?text={{ urlencode('Hi, I want to try Gatezo for my event.') }}" class="btn !text-white ring-1 ring-white/30 hover:bg-white/10">Talk to us on WhatsApp</a>
        </div>
    </div>
</section>

<footer class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-4 px-6 py-8 text-sm text-neutral-500 sm:flex-row sm:items-center">
    <div class="flex items-center gap-2"><img src="/brand/mark.svg" alt="" class="h-6 w-6"><span>Gatezo, by Infrion Technolab, Ahmedabad</span></div>
    <div class="flex gap-6"><a href="/admin/login" class="hover:text-[var(--ink)]">Sign in</a><a href="/scan" class="hover:text-[var(--ink)]">Volunteer scanner</a></div>
</footer>
</body>
</html>
