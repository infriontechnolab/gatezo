<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gatezo — replace the clipboard with a QR code</title>
    <meta name="description" content="Gatezo turns any gate, poster or stall at a local event into a live data point. Attendees scan with their camera, no app. Works when the venue Wi-Fi dies.">
    <meta name="theme-color" content="#E8604C">
    <link rel="icon" href="/brand/mark.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/landing.js'])
</head>
<body class="antialiased">

<header class="relative z-10 mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-8">
    <a href="/" aria-label="Gatezo home"><img src="/brand/logo.png" alt="Gatezo" class="h-8 w-auto sm:h-9"></a>
    <nav class="hidden items-center gap-7 text-[15px] font-medium text-neutral-600 md:flex" aria-label="Sections">
        <a href="#map" class="hover:text-[var(--ink)]">The map</a>
        <a href="#how" class="hover:text-[var(--ink)]">How it works</a>
        <a href="#dashboard" class="hover:text-[var(--ink)]">Dashboard</a>
        <a href="#who" class="hover:text-[var(--ink)]">Who it's for</a>
        <a href="/admin/login" class="hover:text-[var(--ink)]">Sign in</a>
    </nav>
    <a href="/admin/register" class="btn btn-coral !px-5 !py-2.5 !text-[15px]">Start your event</a>
</header>

{{-- ===== Hero: scan → checked in → number ===== --}}
<section class="relative overflow-hidden">
    <div class="hero-bg"><div class="glow glow-coral"></div><div class="glow glow-plum"></div></div>
    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 pb-16 pt-8 sm:px-8 lg:grid-cols-[1.1fr_.9fr] lg:pb-24 lg:pt-14">
        <div>
            <h1 class="display text-[3rem] sm:text-[4.5rem] lg:text-[5.5rem]">Replace the clipboard with a QR code.</h1>
            <p class="mt-6 max-w-lg text-lg leading-relaxed text-neutral-600 sm:text-xl">Print a few sheets, stick them on your gates, posters and stalls. Every scan becomes a live number. Attendees use their phone camera. No app, no account.</p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="/admin/register" class="btn btn-coral">Start your event</a>
                <a href="#how" class="btn btn-ghost">See how it works</a>
            </div>
            <p class="mt-5 text-sm text-neutral-500">Free for events under 300 people. Built in Ahmedabad for fairs, fests, tournaments and garba nights.</p>
        </div>

        <div class="relative mx-auto flex justify-center py-6 lg:justify-end lg:pr-10" aria-hidden="true">
            <div id="hero-phone" class="phone">
                <div class="phone-screen">
                    <div class="cam">
                        <div class="cam-qr">{!! $qr['pass'] !!}</div>
                        <div class="cam-frame"></div>
                        <div class="scanline"></div>
                        <div class="toast">
                            <div class="flex items-center gap-2"><span class="text-lg leading-none">✓</span><div><div class="text-[13px] font-bold leading-tight">Checked in</div><div class="text-[11px] opacity-90"><span id="hero-who">Aarti S.</span> · Main Gate</div></div></div>
                        </div>
                    </div>
                    <div class="phone-bar"><span>Main Gate (G1)</span><span><span class="live-dot"></span> live</span></div>
                </div>
            </div>
            <div id="hero-chip" class="counter-chip">
                <div class="text-[11px] font-semibold text-neutral-500">Inside now</div>
                <div class="num text-3xl font-extrabold leading-none" id="hero-count">1,099</div>
                <div class="mt-1 text-[11px] text-neutral-500">of 1,500 · Sharad Utsav</div>
            </div>
        </div>
    </div>
</section>

{{-- ===== Live ticker ===== --}}
<div class="ticker" aria-label="Live event activity (demo)">
    <div id="ticker" class="ticker-track"></div>
</div>

{{-- ===== Chaos → Gatezo ===== --}}
<section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
    <div class="grid gap-10 lg:grid-cols-[.9fr_1.1fr] lg:items-center">
        <div>
            <h2 class="h2 text-3xl sm:text-4xl lg:text-5xl">Tonight, running the gate looks like this.</h2>
            <p class="mt-5 max-w-md text-lg text-neutral-600">A paper list nobody can read. Six volunteers asking "who's at Gate 2?". Stall owners guessing. Feedback in a box. And tomorrow: "it went well".</p>
            <p class="mt-4 max-w-md text-lg text-neutral-600">Keep scrolling and watch it become one screen.</p>
        </div>
        <div id="chaos" class="chaos" aria-hidden="true">
            <div class="art paper" style="left:2%;top:4%;transform:rotate(-6deg);--tx:180px;--ty:200px">
                <div class="font-bold text-neutral-700" style="font-family:inherit">Entry list · Gate 1</div>
                <div class="hand">Ramesh bhai +3</div><div class="hand">Priya (VIP?)</div><div class="hand">Amit <s>Patel</s> Shah</div><div class="hand">…………</div>
            </div>
            <div class="art bubble" style="left:50%;top:0;transform:rotate(3deg);--tx:-60px;--ty:220px">Who is at Gate 2?? Anyone?<small>9:12 PM</small></div>
            <div class="art bubble out" style="left:58%;top:18%;transform:rotate(-2deg);--tx:-90px;--ty:150px">Bhavesh bhai please come to parking urgently<small>9:14 PM</small></div>
            <div class="art sticky-note" style="left:8%;top:52%;transform:rotate(5deg);--tx:170px;--ty:-60px">Kulfi stall wants to know how many people came??</div>
            <div class="art bubble" style="left:38%;top:62%;transform:rotate(-4deg);--tx:20px;--ty:-90px">How many inside right now? Police asking<small>9:31 PM</small></div>
            <div class="art slip" style="left:66%;top:56%;transform:rotate(7deg);--tx:-120px;--ty:-80px"><b>Feedback</b><br>Sound too loud near stage. Parking is a mess. Otherwise good!<br><span class="text-neutral-400">— found in the box, 4 days later</span></div>
            <div class="gatezo-card">
                <div class="flex items-center justify-between text-xs text-neutral-500"><span class="font-semibold text-[var(--ink)]">Sharad Utsav · live</span><span><span class="live-dot"></span> 9:31 PM</span></div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-[var(--stone)] p-2"><div class="num text-2xl font-extrabold">1,099</div><div class="text-[10px] text-neutral-500">inside now</div></div>
                    <div class="rounded-xl bg-[var(--stone)] p-2"><div class="num text-2xl font-extrabold">7</div><div class="text-[10px] text-neutral-500">on duty</div></div>
                    <div class="rounded-xl bg-[var(--stone)] p-2"><div class="num text-2xl font-extrabold">4.1★</div><div class="text-[10px] text-neutral-500">feedback</div></div>
                </div>
                <div class="mt-3 space-y-1 text-[12px]">
                    <div class="flex justify-between"><span>Gate 2 · Amit, Meera</span><span class="text-neutral-500">317 in</span></div>
                    <div class="flex justify-between"><span>Kesar Kulfi</span><span class="text-neutral-500">278 scans · 18 leads</span></div>
                    <div class="flex justify-between"><span>Parking · Nirav</span><span class="text-neutral-500">on duty 9:02</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===== Event map: the signature visual ===== --}}
<section id="map" class="bg-[var(--plum-deep)] text-white">
    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
        <div class="max-w-2xl">
            <h2 class="h2 text-3xl sm:text-4xl lg:text-5xl">Stick a QR on it. Every scan becomes a data point.</h2>
            <p class="mt-4 text-lg text-white/70">This is a real event layout. Coral markers are scanned by attendees' own cameras; plum ones by volunteers with the scanner. Every dot you see travelling is one scan landing on the organizer's screen. Hover or tap a marker.</p>
        </div>
        <div id="map-wrap" class="map-wrap mt-10 text-[var(--ink)]">
            <svg class="map" viewBox="0 0 1200 620" role="img" aria-label="Map of an event ground with gates, stalls, stage and exit, each with a QR marker">
                <path class="ground" d="M90 110 Q110 60 170 60 H1010 Q1090 60 1100 130 V520 Q1095 580 1030 580 H150 Q90 580 90 520 Z"/>
                <path class="lane" d="M120 330 H1080"/><path class="lane" d="M330 90 V560"/><path class="lane" d="M870 90 V560"/>
                {{-- stage --}}
                <rect x="470" y="110" width="260" height="90" rx="14" fill="#6B2D5C" opacity=".9"/><text x="600" y="162" text-anchor="middle" class="label" fill="#fff" style="fill:#fff;font-size:15px">Stage</text>
                {{-- stalls row --}}
                @foreach ([['Chai Point',380],['Kesar Kulfi',480],['Pav Bhaji',580],['Chaniya Choli',680],['Jewellery',780]] as [$n,$x])
                    <rect class="stall" x="{{ $x }}" y="410" width="90" height="60" rx="10"/><text x="{{ $x+45 }}" y="446" text-anchor="middle" class="label sm">{{ $n }}</text>
                @endforeach
                <text x="622" y="398" text-anchor="middle" class="label sm">Food court &amp; stalls</text>
                {{-- entrance road --}}
                <path d="M0 330 H90" stroke="#d6d3d1" stroke-width="16" stroke-linecap="round"/>
                <text x="130" y="130" class="label">Parking</text><text x="960" y="130" class="label">VIP lawn</text>
                {{-- markers: attendee (coral) --}}
                <g class="marker att" data-tip="Registration poster · attendees register here" tabindex="0" transform="translate(40 300)"><circle class="ring" r="12"/><circle class="core" r="12"/><rect class="qr" x="-5" y="-5" width="10" height="10" rx="1.5"/></g>
                <text x="40" y="340" text-anchor="middle" class="label sm">Poster</text>
                @foreach ([425,525,625,725,825] as $x)
                    <g class="marker att" data-tip="Stall card · attendee sees menu & offers" tabindex="0" transform="translate({{ $x }} 405)"><circle class="ring" r="9"/><circle class="core" r="9"/><rect class="qr" x="-3.5" y="-3.5" width="7" height="7" rx="1"/></g>
                @endforeach
                <g class="marker att" data-tip="Exit feedback card · one-tap rating" tabindex="0" transform="translate(1100 520)"><circle class="ring" r="12"/><circle class="core" r="12"/><rect class="qr" x="-5" y="-5" width="10" height="10" rx="1.5"/></g>
                <text x="1100" y="560" text-anchor="middle" class="label sm">Exit</text>
                {{-- markers: volunteer gates (plum) --}}
                <g class="marker vol" data-gate="G1" data-tip="Main Gate · volunteer scans passes" tabindex="0" transform="translate(120 330)"><circle class="ring" r="14"/><circle class="core" r="14"/><rect class="qr" x="-6" y="-6" width="12" height="12" rx="2"/></g>
                <g class="marker vol" data-gate="G2" data-tip="Tower B Gate · volunteer scans passes" tabindex="0" transform="translate(600 580)"><circle class="ring" r="14"/><circle class="core" r="14"/><rect class="qr" x="-6" y="-6" width="12" height="12" rx="2"/></g>
                <g class="marker vol" data-gate="G3" data-tip="VIP Entry · volunteer scans passes" tabindex="0" transform="translate(1080 200)"><circle class="ring" r="14"/><circle class="core" r="14"/><rect class="qr" x="-6" y="-6" width="12" height="12" rx="2"/></g>
                <g class="marker vol" data-tip="Zone sign · volunteer marks 'on duty'" tabindex="0" transform="translate(180 500)"><circle class="ring" r="9"/><circle class="core" r="9"/><rect class="qr" x="-3.5" y="-3.5" width="7" height="7" rx="1"/></g>
                <text x="180" y="530" text-anchor="middle" class="label sm">Parking duty</text>
            </svg>
            <div class="gate-badge" data-gate="G1" style="left:10%;top:47%"><span class="num">886</span> <span class="text-neutral-500">G1</span></div>
            <div class="gate-badge" data-gate="G2" style="left:50%;top:86%"><span class="num">317</span> <span class="text-neutral-500">G2</span></div>
            <div class="gate-badge" data-gate="G3" style="left:90%;top:26%"><span class="num">45</span> <span class="text-neutral-500">G3</span></div>
            <div id="map-inside" class="live-pill">
                <div class="text-[11px] text-neutral-400">Inside now · organizer screen</div>
                <div class="num text-3xl font-extrabold leading-none">1,099</div>
                <div class="mt-1 text-[11px] text-neutral-400"><span class="live-dot"></span> updates every few seconds</div>
            </div>
            <div id="map-tip" class="map-tip"></div>
        </div>
        <div class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-sm text-white/70">
            <span><i class="mr-2 inline-block h-3 w-3 rounded-full bg-[var(--coral)] align-middle"></i>Attendee scans with their camera</span>
            <span><i class="mr-2 inline-block h-3 w-3 rounded-full bg-[#a5648f] align-middle"></i>Volunteer scans with the Gatezo scanner</span>
            <span><i class="mr-2 inline-block h-3 w-3 rounded-full bg-white align-middle"></i>Each moving dot is one scan reaching the organizer</span>
        </div>
    </div>
</section>

{{-- ===== How it works: scroll-driven story ===== --}}
<section id="how" class="story">
    <div class="story-sticky">
        <div class="mx-auto w-full max-w-7xl px-5 sm:px-8">
            <div class="flex items-end justify-between gap-6">
                <h2 class="h2 text-3xl sm:text-4xl lg:text-5xl">Four things happen. You do one of them.</h2>
                <div class="hidden w-40 lg:block"><div class="story-progress"><i id="story-bar"></i></div></div>
            </div>
            <div id="story-track" class="story-track mt-8 lg:mt-12">
                <article class="story-step">
                    <div class="text-sm font-semibold text-neutral-500">1 · You print</div>
                    <h3 class="mt-1 text-2xl font-bold">Stick the sheet on the gate.</h3>
                    <div class="stage"><div class="sheet"><div class="stripe"></div><div class="text-xs font-bold">Scan to get your entry pass</div><div class="mt-2">{!! $qr['poster'] !!}</div><div class="mt-1 text-[10px] text-neutral-500">Free entry · takes 20 seconds</div></div></div>
                </article>
                <article class="story-step">
                    <div class="text-sm font-semibold text-neutral-500">2 · They scan</div>
                    <h3 class="mt-1 text-2xl font-bold">Attendee gets a pass. Volunteer scans it.</h3>
                    <div class="stage"><div class="phone" style="width:150px;border-radius:1.6rem;padding:6px;box-shadow:0 20px 40px -20px rgba(28,25,23,.5), inset 0 0 0 2px #2a2a2a"><div class="phone-screen" style="inset:6px;border-radius:1.3rem"><div class="cam"><div class="cam-qr" style="width:64%">{!! $qr['pass'] !!}</div><div class="cam-frame"></div></div><div class="phone-bar" style="font-size:9px;padding:6px 8px"><span>G1</span><span>live</span></div></div></div></div>
                </article>
                <article class="story-step">
                    <div class="text-sm font-semibold text-neutral-500">3 · Gatezo records</div>
                    <h3 class="mt-1 text-2xl font-bold">Even with no signal. It syncs later.</h3>
                    <div class="stage"><div class="w-full max-w-[300px] space-y-2">
                        <div class="logrow"><span class="num text-neutral-500">9:31:04</span><span>Aarti S.</span><span class="st">ok</span></div>
                        <div class="logrow"><span class="num text-neutral-500">9:31:09</span><span>Bhavesh P.</span><span class="st">ok</span></div>
                        <div class="logrow"><span class="num text-neutral-500">9:31:12</span><span>Aarti S.</span><span class="st" style="color:#b7791f">duplicate</span></div>
                        <div class="logrow"><span class="num text-neutral-500">9:31:20</span><span>Chirag M.</span><span class="st" style="color:#78716c">queued · offline</span></div>
                    </div></div>
                </article>
                <article class="story-step">
                    <div class="text-sm font-semibold text-neutral-500">4 · You watch</div>
                    <h3 class="mt-1 text-2xl font-bold">One number on your phone, and on the gate tablet.</h3>
                    <div class="stage"><div class="rounded-2xl bg-[var(--ink)] p-5 text-white" style="min-width:230px"><div class="text-[11px] text-neutral-400">Inside now</div><div class="num text-5xl font-extrabold leading-none">1,099</div><div class="mt-3 h-2 rounded-full bg-neutral-800"><div class="h-full w-[73%] rounded-full bg-[var(--coral)]"></div></div><div class="mt-1 text-[11px] text-neutral-400">73% of 1,500 · peak 9:15 PM</div></div></div>
                </article>
            </div>
        </div>
    </div>
</section>

{{-- ===== Dashboard bento ===== --}}
<section id="dashboard" class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
    <div class="max-w-2xl"><h2 class="h2 text-3xl sm:text-4xl lg:text-5xl">The whole event on one screen.</h2><p class="mt-4 text-lg text-neutral-600">These are the numbers from a real demo event, drawn live as you scroll.</p></div>
    <div id="bento" class="bento mt-10">
        <div class="tile t-gauge reveal">
            <h3>Inside now</h3>
            <svg class="gauge" viewBox="0 0 200 120" aria-hidden="true"><path class="track" d="M20 110 A80 80 0 0 1 180 110"/><path class="arc" data-pct="73" d="M20 110 A80 80 0 0 1 180 110"/></svg>
            <div class="-mt-10 text-center"><div class="num text-5xl font-extrabold leading-none" data-count="1099">0</div><div class="mt-1 text-sm text-neutral-500">73% of 1,500 capacity · counting people, not scans</div></div>
            <div class="mt-5 grid grid-cols-2 gap-3 text-center text-sm"><div class="rounded-xl bg-[var(--stone)] p-3"><div class="num text-xl font-bold" data-count="1120">0</div><div class="text-neutral-500">checked in</div></div><div class="rounded-xl bg-[var(--stone)] p-3"><div class="num text-xl font-bold" data-count="1500">0</div><div class="text-neutral-500">registered</div></div></div>
        </div>
        <div class="tile t-gates reveal">
            <h3>Arrivals per 15 minutes · peak 9:15 PM</h3>
            <div class="bars" aria-hidden="true">@foreach ([2,8,17,38,57,70,96,97,74,60,44,27,15,7,3,1,1] as $i => $h)<i style="--i:{{ $i }}" data-h="{{ $h }}"></i>@endforeach</div>
            <div class="mt-2 flex justify-between text-[11px] text-neutral-500"><span>7:30 PM</span><span>11:30 PM</span></div>
        </div>
        <div class="tile t-duty reveal">
            <h3>Who's where</h3>
            <div class="duty"><span>Ravi · Main Gate</span><span class="pill">On duty</span></div>
            <div class="duty"><span>Meera · Tower B</span><span class="pill">On duty</span></div>
            <div class="duty"><span>Nirav · Parking</span><span class="pill">On duty</span></div>
            <div class="duty"><span class="text-neutral-500">Jay · Stage</span><span class="text-xs font-semibold text-[var(--coral)]">not arrived</span></div>
        </div>
        <div class="tile t-scans reveal">
            <h3>Entry scans</h3>
            <div class="num big" data-count="1248">0</div>
            <div class="mt-2 text-sm text-neutral-500"><b class="num" data-count="24">0</b> flagged as duplicates, none blocked</div>
        </div>
        <div class="tile t-feedback reveal">
            <h3>Feedback · 4.1 ★ from 100</h3>
            <svg class="spark" viewBox="0 0 300 70" preserveAspectRatio="none" aria-hidden="true"><path class="a" d="M0 60 L30 52 L60 55 L90 40 L120 44 L150 30 L180 34 L210 22 L240 26 L270 14 L300 18 V70 H0 Z"/><path class="l" d="M0 60 L30 52 L60 55 L90 40 L120 44 L150 30 L180 34 L210 22 L240 26 L270 14 L300 18"/></svg>
            <div class="mt-2"><div class="hbar"><span>5 ★</span><i data-w="46"></i><span class="num">46</span></div><div class="hbar"><span>4 ★</span><i data-w="31"></i><span class="num">31</span></div><div class="hbar"><span>3 ★ or less</span><i data-w="23"></i><span class="num">23</span></div></div>
        </div>
        <div class="tile t-report reveal">
            <h3>Post-event report</h3>
            <div class="report-sheet"><b>Sharad Utsav Garba 2026</b><br>1,120 attended · 75% showed up<br>Peak 9:15 PM · 194 in 15 min<br>Gates: G1 71% · G2 25% · G3 4%<br>Feedback 4.1 ★ · 6 stalls · 139 leads</div>
            <a href="#" class="btn btn-ghost mt-4 w-full !py-2.5 !text-sm" tabindex="-1" aria-disabled="true">Print / save as PDF</a>
            <div class="mt-2 text-center text-xs text-neutral-500">Plus attendees CSV and vendor lead lists</div>
        </div>
        <div class="tile t-draw reveal">
            <h3>Lucky draw</h3>
            <div class="text-sm">Winner picked from people <b>inside right now</b>. Seed fingerprint published before the draw.</div>
            <div class="mt-3 rounded-xl bg-[var(--ink)] p-3 text-white"><div class="text-[10px] text-neutral-400">Mixer grinder</div><div class="text-lg font-bold">Kunal B. · 98xxxx0005</div></div>
        </div>
    </div>
</section>

{{-- ===== Principles ===== --}}
<section class="bg-white">
    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
        <h2 class="h2 max-w-2xl text-3xl sm:text-4xl lg:text-5xl">Built for a ground with bad Wi-Fi and a committee with no budget.</h2>
        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="principle" tabindex="0">
                <div class="demo"><div class="s1"><div class="chip">📷 Phone camera</div></div><div class="s2"><div class="chip" style="border:1.5px dashed var(--ink)">🎟 Pass on their phone</div></div></div>
                <div class="text-lg font-bold">No app, no account</div><p class="mt-1 text-[15px] text-neutral-600">Attendees scan with the camera they already have. If it fails, the volunteer types the 8-letter code.</p>
            </div>
            <div class="principle" tabindex="0">
                <div class="demo"><div class="s1"><div class="chip"><span class="wifi-off">📶</span> Wi-Fi died · 3 scans queued</div></div><div class="s2"><div class="chip" style="color:var(--ok)">✓ Back online · 3 synced</div></div></div>
                <div class="text-lg font-bold">Keeps working offline</div><p class="mt-1 text-[15px] text-neutral-600">Scanners cache the list before doors open. Scans queue on the phone and sync when the signal is back.</p>
            </div>
            <div class="principle" tabindex="0">
                <div class="demo"><div class="s1"><div class="chip">Society garba · once a year</div></div><div class="s2"><div class="chip"><b>₹0</b> /month · pay per event</div></div></div>
                <div class="text-lg font-bold">Pay only for events you run</div><p class="mt-1 text-[15px] text-neutral-600">Free under 300 attendees. A flat price per event above that. No subscription for one night a year.</p>
            </div>
            <div class="principle" tabindex="0">
                <div class="demo"><div class="s1"><div class="chip">Scan at Gate 2…</div></div><div class="s2"><div class="chip"><span class="live-dot"></span> 1,100 inside · 3 s later</div></div></div>
                <div class="text-lg font-bold">Live, not "refresh and wait"</div><p class="mt-1 text-[15px] text-neutral-600">Dashboard and gate board update every few seconds. A stale headcount is worse than none.</p>
            </div>
        </div>
    </div>
</section>

{{-- ===== Use cases: drawn scenes ===== --}}
<section id="who" class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
    <h2 class="h2 max-w-2xl text-3xl sm:text-4xl">Made for the events that don't have a ticketing budget.</h2>
    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="scene" tabindex="0">
            <svg viewBox="0 0 300 150" aria-hidden="true"><g class="crowd" fill="#c9bfb6">@for($i=0;$i<14;$i++)<circle cx="{{ 30+$i*20 }}" cy="{{ 128+($i%2)*6 }}" r="5"/>@endfor</g>
                <path d="M40 110 L80 55 L120 110 Z" fill="#E8604C"/><path d="M110 110 L150 50 L190 110 Z" fill="#6B2D5C"/><path d="M180 110 L220 58 L260 110 Z" fill="#E8604C"/>
                <circle class="lamp" cx="150" cy="30" r="6" fill="#d6d3d1" opacity=".6"/><circle class="lamp" cx="80" cy="36" r="4" fill="#d6d3d1" opacity=".6"/><circle class="lamp" cx="220" cy="38" r="4" fill="#d6d3d1" opacity=".6"/></svg>
            <div class="p-5"><div class="text-lg font-bold">Community fairs &amp; melas</div><p class="mt-1 text-[15px] text-neutral-600">Stalls, a food court, a lucky draw and a lot of walk-ups.</p><div class="mt-3 flex items-baseline gap-2"><span class="n num" data-base="1120" data-hot="1500">1,120</span><span class="text-sm text-neutral-500">inside at peak</span></div></div>
        </div>
        <div class="scene" tabindex="0">
            <svg viewBox="0 0 300 150" aria-hidden="true"><rect x="60" y="40" width="180" height="60" rx="8" fill="#6B2D5C"/><rect x="75" y="52" width="150" height="8" rx="4" fill="#fff" opacity=".35"/><rect x="75" y="68" width="100" height="8" rx="4" fill="#fff" opacity=".35"/>
                <g class="crowd" fill="#c9bfb6">@for($i=0;$i<12;$i++)<circle cx="{{ 45+$i*20 }}" cy="{{ 124+($i%3)*5 }}" r="5"/>@endfor</g>
                <circle class="lamp" cx="60" cy="30" r="5" fill="#d6d3d1" opacity=".6"/><circle class="lamp" cx="240" cy="30" r="5" fill="#d6d3d1" opacity=".6"/></svg>
            <div class="p-5"><div class="text-lg font-bold">College fests</div><p class="mt-1 text-[15px] text-neutral-600">Multiple venues, volunteer shifts, sponsors who want numbers.</p><div class="mt-3 flex items-baseline gap-2"><span class="n num" data-base="34" data-hot="40">34</span><span class="text-sm text-neutral-500">volunteers on duty</span></div></div>
        </div>
        <div class="scene" tabindex="0">
            <svg viewBox="0 0 300 150" aria-hidden="true"><ellipse cx="150" cy="95" rx="120" ry="42" fill="#dfe9d8"/><ellipse cx="150" cy="95" rx="70" ry="24" fill="none" stroke="#fff" stroke-width="2"/><line x1="150" y1="53" x2="150" y2="137" stroke="#fff" stroke-width="2"/>
                <g class="crowd" fill="#c9bfb6">@for($i=0;$i<10;$i++)<circle cx="{{ 60+$i*20 }}" cy="{{ 30+($i%2)*6 }}" r="5"/>@endfor</g>
                <circle class="lamp" cx="40" cy="60" r="5" fill="#d6d3d1" opacity=".6"/><circle class="lamp" cx="260" cy="60" r="5" fill="#d6d3d1" opacity=".6"/></svg>
            <div class="p-5"><div class="text-lg font-bold">Sports tournaments</div><p class="mt-1 text-[15px] text-neutral-600">Per-ground gates, re-entry, and a headcount for the club.</p><div class="mt-3 flex items-baseline gap-2"><span class="n num" data-base="3" data-hot="5">3</span><span class="text-sm text-neutral-500">grounds, one screen</span></div></div>
        </div>
        <div class="scene" tabindex="0">
            <svg viewBox="0 0 300 150" aria-hidden="true"><path d="M110 120 V70 Q150 25 190 70 V120 Z" fill="#E8604C"/><path d="M150 50 Q120 50 110 30 Q150 15 190 30 Q180 50 150 50" fill="#6B2D5C"/>
                <g class="crowd" fill="#c9bfb6">@for($i=0;$i<14;$i++)<circle cx="{{ 30+$i*20 }}" cy="{{ 130+($i%2)*5 }}" r="5"/>@endfor</g>
                <circle class="lamp" cx="70" cy="90" r="4" fill="#d6d3d1" opacity=".6"/><circle class="lamp" cx="230" cy="90" r="4" fill="#d6d3d1" opacity=".6"/><circle class="lamp" cx="150" cy="95" r="5" fill="#d6d3d1" opacity=".6"/></svg>
            <div class="p-5"><div class="text-lg font-bold">Religious gatherings</div><p class="mt-1 text-[15px] text-neutral-600">Capacity you can show the police, and prasad counters that know the crowd.</p><div class="mt-3 flex items-baseline gap-2"><span class="n num" data-base="1500" data-hot="1500">1,500</span><span class="text-sm text-neutral-500">capacity, enforced by you</span></div></div>
        </div>
    </div>
</section>

{{-- ===== Final CTA ===== --}}
<section class="bg-[var(--plum-deep)] text-white">
    <div class="mx-auto max-w-7xl px-5 py-20 text-center sm:px-8 lg:py-28">
        <h2 class="h2 mx-auto max-w-3xl text-3xl sm:text-4xl lg:text-5xl">Run your next event without the clipboard.</h2>
        <p class="mx-auto mt-5 max-w-xl text-lg text-white/70">Create the event, print the kit, forward one link on WhatsApp. That's the setup.</p>
        <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
            <a href="/admin/register" class="btn btn-white">Start your event</a>
            <a href="https://wa.me/919328964742?text={{ urlencode('Hi, I want to try Gatezo for my event.') }}" class="btn btn-outline-white">Talk to us on WhatsApp</a>
        </div>
    </div>
</section>

<footer class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-4 px-5 py-8 text-sm text-neutral-500 sm:flex-row sm:items-center sm:px-8">
    <div class="flex items-center gap-2"><img src="/brand/mark.png" alt="" class="h-6 w-6"><span>Gatezo, by Infrion Technolab, Ahmedabad</span></div>
    <div class="flex gap-6"><a href="/admin/login" class="hover:text-[var(--ink)]">Sign in</a><a href="/scan" class="hover:text-[var(--ink)]">Volunteer scanner</a></div>
</footer>
</body>
</html>
