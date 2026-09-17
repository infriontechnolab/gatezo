<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gatezo — replace the clipboard with a QR code</title>
    <meta name="description" content="Gatezo turns any gate, poster or stall at a local event into a live data point. Attendees scan with their camera, no app. Works when the venue Wi-Fi dies.">
    <meta name="theme-color" content="#E8604C">
    {{-- Link previews (WhatsApp, LinkedIn, X). Card rendered by resources/og/render.sh. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Gatezo">
    <meta property="og:title" content="Gatezo — replace the clipboard with a QR">
    <meta property="og:description" content="Print a few QR sheets, stick them on gates, posters and stalls. Attendees scan with their camera, volunteers scan passes offline, you watch one live number.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ url('/og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="/brand/mark.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wdth,wght@12..96,75..100,400;12..96,75..100,600;12..96,75..100,700;12..96,75..100,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/landing.css', 'resources/js/landing.js'])
</head>
<body class="antialiased">

{{-- Slim bar that slides in once the hero header has scrolled away. Anchors, not pages, so mobile keeps only mark + CTA. --}}
<div id="topbar" class="topbar" aria-hidden="true">
    <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-5 sm:px-8">
        <a href="#" class="flex items-center gap-2" aria-label="Back to top"><img src="/brand/mark.png" alt="" class="h-7 w-7"><span class="hidden text-[15px] font-extrabold sm:inline">Gatezo</span></a>
        <nav class="topbar-nav hidden items-center gap-6 text-[14px] font-semibold text-neutral-600 md:flex" aria-label="Sections">
            <a href="#map">The map</a>
            <a href="#how">How it works</a>
            <a href="#dashboard">Dashboard</a>
            <a href="#who">Who it's for</a>
        </nav>
        <div class="flex items-center gap-4">
            <a href="/admin/login" class="hidden text-[14px] font-semibold text-neutral-600 hover:text-[var(--ink)] sm:inline">Sign in</a>
            <a href="{{ $wa }}" class="btn btn-ink !px-4 !py-2 !text-[14px]">Start your event</a>
        </div>
    </div>
    <i class="topbar-progress" aria-hidden="true"></i>
</div>

<header class="relative z-10 mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-8">
    <a href="/" aria-label="Gatezo home"><img src="/brand/logo.png" alt="Gatezo" class="h-8 w-auto sm:h-9"></a>
    <nav class="hidden items-center gap-7 text-[15px] font-semibold text-neutral-600 md:flex" aria-label="Sections">
        <a href="#map" class="hover:text-[var(--ink)]">The map</a>
        <a href="#how" class="hover:text-[var(--ink)]">How it works</a>
        <a href="#dashboard" class="hover:text-[var(--ink)]">Dashboard</a>
        <a href="#who" class="hover:text-[var(--ink)]">Who it's for</a>
        <a href="/admin/login" class="hover:text-[var(--ink)]">Sign in</a>
    </nav>
    <a href="{{ $wa }}" class="btn btn-ink !px-5 !py-2.5 !text-[15px]">Start your event</a>
</header>

{{-- ===== Hero: one sheet. Headline across the full width, the real poster taped on top, the scanner phone over it. ===== --}}
<section class="hero">
    <div class="hero-noise"></div><div class="hero-glow"></div><div class="hero-glow plum"></div>
    <div class="relative mx-auto max-w-7xl px-5 pb-16 pt-6 sm:px-8 lg:pb-24 lg:pt-10">
        <h1 class="display hero-title">
            <span class="row">Replace</span>
            <span class="row indent">the clipboard</span>
            <span class="row">with a QR.</span>
        </h1>
        <div class="mt-8 grid gap-10 lg:mt-12 lg:grid-cols-[1fr_1fr] lg:items-start">
            <div class="max-w-md lg:pt-6">
                <p class="text-lg leading-relaxed text-neutral-700 sm:text-xl">Print a few sheets. Stick them on your gates, posters and stalls. Every scan becomes a live number on your phone. Attendees use their camera. No app, no account.</p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a href="{{ $wa }}" class="btn btn-coral">Start your event</a>
                    <a href="/demo" class="btn btn-ghost">Open the live demo</a>
                </div>
                <div class="mt-7 flex items-center gap-4"><span class="stamp">Free under 300 people</span><span class="text-sm text-neutral-500">Built in Ahmedabad for fairs, fests, tournaments and garba nights.</span></div>
            </div>
            <div class="hero-stage" aria-hidden="true">
                <div class="sheet cut poster">
                    <span class="tape"></span>
                    <div class="stripe"></div>
                    <div class="mt-4 text-[11px] font-bold text-neutral-500">Scan to get your entry pass</div>
                    <div class="text-2xl font-extrabold leading-tight">Sharad Utsav Garba</div>
                    <div class="qr mt-3">{!! $qr['poster'] !!}</div>
                    <div class="mt-3 text-[11px] text-neutral-500">Free entry · no app · 20 seconds</div>
                </div>
                <div id="hero-phone" class="phone">
                    <div class="phone-screen">
                        <div class="cam">
                            <div class="cam-qr">{!! $qr['pass'] !!}</div>
                            <div class="cam-frame"></div><div class="scanline"></div>
                            <div class="toast"><div class="flex items-center gap-2"><span class="text-base leading-none">✓</span><div><div class="text-[12px] font-bold leading-tight">Checked in</div><div class="text-[10px] opacity-90"><span id="hero-who">Aarti S.</span> · Main Gate</div></div></div></div>
                        </div>
                        <div class="phone-bar"><span>Main Gate (G1)</span><span><span class="live-dot"></span> live</span></div>
                    </div>
                </div>
                <div id="hero-chip" class="counter-stub">
                    <div class="text-[10px] font-semibold text-neutral-400">Inside now</div>
                    <div class="num text-3xl font-extrabold leading-none" id="hero-count">1,099</div>
                    <div class="mt-1 text-[10px] text-neutral-400">of 1,500 · Sharad Utsav</div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="ticker" aria-label="Live event activity (demo)"><div id="ticker" class="ticker-track"></div></div>

{{-- ===== Chaos → one screen ===== --}}
<section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
    <div class="grid gap-10 lg:grid-cols-[.85fr_1.15fr] lg:items-center">
        <div class="rise">
            <h2 class="h2">Tonight, running the gate looks like this.</h2>
            <p class="mt-5 max-w-md text-lg text-neutral-600">A paper list nobody can read. Six volunteers asking "who's at Gate 2?". Stall owners guessing. Feedback in a box. Tomorrow: "it went well".</p>
            <p class="mt-4 max-w-md text-lg text-neutral-600">Scroll on, and watch all of it fold into one screen.</p>
        </div>
        <div id="chaos" class="chaos" aria-hidden="true">
            <div class="art paper" style="left:2%;top:4%;transform:rotate(-6deg);--tx:180px;--ty:200px"><div class="font-bold text-neutral-700">Entry list · Gate 1</div><div class="hand">Ramesh bhai +3</div><div class="hand">Priya (VIP?)</div><div class="hand">Amit <s>Patel</s> Shah</div><div class="hand">…………</div></div>
            <div class="art bubble" style="left:50%;top:0;transform:rotate(3deg);--tx:-60px;--ty:220px">Who is at Gate 2?? Anyone?<small>9:12 PM</small></div>
            <div class="art bubble out" style="left:58%;top:18%;transform:rotate(-2deg);--tx:-90px;--ty:150px">Bhavesh bhai please come to parking urgently<small>9:14 PM</small></div>
            <div class="art sticky-note" style="left:8%;top:54%;transform:rotate(5deg);--tx:170px;--ty:-60px">Kulfi stall wants to know how many people came??</div>
            <div class="art bubble" style="left:38%;top:64%;transform:rotate(-4deg);--tx:20px;--ty:-90px">How many inside right now? Police asking<small>9:31 PM</small></div>
            <div class="art slip" style="left:66%;top:58%;transform:rotate(7deg);--tx:-120px;--ty:-80px"><b>Feedback</b><br>Sound too loud near stage. Parking is a mess. Otherwise good!<br><span class="text-neutral-400">— found in the box, 4 days later</span></div>
            <div class="gatezo-card">
                <div class="flex items-center justify-between text-xs text-neutral-500"><span class="font-bold text-[var(--ink)]">Sharad Utsav · live</span><span><span class="live-dot"></span> 9:31 PM</span></div>
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

{{-- ===== Event map ===== --}}
<section id="map" class="bg-[var(--plum-deep)] text-white">
    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
        <div class="max-w-2xl rise">
            <h2 class="h2">Stick a QR on it. Every scan becomes a data point.</h2>
            <p class="mt-4 text-lg text-white/70">A real ground plan. Coral markers are scanned by attendees' own cameras; plum ones by volunteers with the scanner. Every travelling dot is one scan landing on the organizer's screen. Hover or tap a marker.</p>
        </div>
        <div id="map-wrap" class="map-wrap mt-10 text-[var(--ink)]">
            <svg class="map" viewBox="0 0 1200 620" role="img" aria-label="Map of an event ground with gates, stalls, stage and exit, each with a QR marker">
                <path class="ground" d="M90 110 Q110 60 170 60 H1010 Q1090 60 1100 130 V520 Q1095 580 1030 580 H150 Q90 580 90 520 Z"/>
                <path class="lane" d="M120 330 H1080"/><path class="lane" d="M330 90 V560"/><path class="lane" d="M870 90 V560"/>
                <rect x="470" y="110" width="260" height="90" rx="14" fill="#6B2D5C" opacity=".9"/><text x="600" y="162" text-anchor="middle" style="fill:#fff;font:700 15px 'Bricolage Grotesque',sans-serif">Stage</text>
                @foreach ([['Chai Point',380],['Kesar Kulfi',480],['Pav Bhaji',580],['Chaniya Choli',680],['Jewellery',780]] as [$n,$x])
                    <rect class="stall" x="{{ $x }}" y="410" width="90" height="60" rx="10"/><text x="{{ $x+45 }}" y="446" text-anchor="middle" class="label sm">{{ $n }}</text>
                @endforeach
                <text x="622" y="398" text-anchor="middle" class="label sm">Food court &amp; stalls</text>
                <path d="M0 330 H90" stroke="#d6d3d1" stroke-width="16" stroke-linecap="round"/>
                <text x="130" y="130" class="label">Parking</text><text x="960" y="130" class="label">VIP lawn</text>
                <g class="marker att" data-tip="Registration poster · attendees register here" tabindex="0" transform="translate(40 300)"><circle class="ring" r="12"/><circle class="core" r="12"/><rect class="qr" x="-5" y="-5" width="10" height="10" rx="1.5"/></g>
                <text x="40" y="340" text-anchor="middle" class="label sm">Poster</text>
                @foreach ([425,525,625,725,825] as $x)
                    <g class="marker att" data-tip="Stall card · attendee sees menu & offers" tabindex="0" transform="translate({{ $x }} 405)"><circle class="ring" r="9"/><circle class="core" r="9"/><rect class="qr" x="-3.5" y="-3.5" width="7" height="7" rx="1"/></g>
                @endforeach
                <g class="marker att" data-tip="Exit feedback card · one-tap rating" tabindex="0" transform="translate(1100 520)"><circle class="ring" r="12"/><circle class="core" r="12"/><rect class="qr" x="-5" y="-5" width="10" height="10" rx="1.5"/></g>
                <text x="1100" y="560" text-anchor="middle" class="label sm">Exit</text>
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

{{-- ===== How it works: sheets stack as you scroll ===== --}}
<section id="how" class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
    <h2 class="h2 rise max-w-3xl">Four things happen. You do one of them.</h2>
    <div class="stack mt-10">
        <article class="step" style="--i:0">
            <div><div class="n">1</div><h3 class="mt-3 text-2xl font-extrabold sm:text-3xl">You print the kit and stick it on the gate.</h3><p class="mt-3 max-w-sm text-neutral-600">Poster, gate signs, stall cards, exit cards. Black on white, any printer, four sheets for a small event.</p></div>
            <div class="stage"><div class="mini-sheet"><div class="stripe"></div><div class="mt-2 text-xs font-bold">Scan to get your entry pass</div><div class="mt-2">{!! $qr['poster'] !!}</div><div class="mt-1 text-[10px] text-neutral-500">Free entry · takes 20 seconds</div></div></div>
        </article>
        <article class="step" style="--i:1">
            <div><div class="n plum">2</div><h3 class="mt-3 text-2xl font-extrabold sm:text-3xl">Attendees get a pass. Your volunteer scans it.</h3><p class="mt-3 max-w-sm text-neutral-600">The pass is a signed QR on their phone. The scanner is any phone with the 6-digit event code; no accounts, no training.</p></div>
            <div class="stage"><div class="phone" style="position:relative;width:150px;transform:rotate(3deg);border-radius:1.6rem;padding:6px"><div class="phone-screen" style="inset:6px;border-radius:1.3rem"><div class="cam"><div class="cam-qr" style="width:64%">{!! $qr['pass'] !!}</div><div class="cam-frame"></div></div><div class="phone-bar" style="font-size:9px;padding:6px 8px"><span>G1</span><span>live</span></div></div></div></div>
        </article>
        <article class="step" style="--i:2">
            <div><div class="n">3</div><h3 class="mt-3 text-2xl font-extrabold sm:text-3xl">Gatezo records it, even with no signal.</h3><p class="mt-3 max-w-sm text-neutral-600">Scans are checked on the phone and queued. They sync when the signal is back. A second scan of the same pass is flagged, never blocked.</p></div>
            <div class="stage"><div class="w-full max-w-[300px] space-y-2">
                <div class="logrow"><span class="num text-neutral-500">9:31:04</span><span>Aarti S.</span><span class="st">ok</span></div>
                <div class="logrow"><span class="num text-neutral-500">9:31:09</span><span>Bhavesh P.</span><span class="st">ok</span></div>
                <div class="logrow"><span class="num text-neutral-500">9:31:12</span><span>Aarti S.</span><span class="st" style="color:#b7791f">duplicate</span></div>
                <div class="logrow"><span class="num text-neutral-500">9:31:20</span><span>Chirag M.</span><span class="st" style="color:#78716c">queued · offline</span></div>
            </div></div>
        </article>
        <article class="step" style="--i:3">
            <div><div class="n plum">4</div><h3 class="mt-3 text-2xl font-extrabold sm:text-3xl">You watch one number. So does the gate tablet.</h3><p class="mt-3 max-w-sm text-neutral-600">Inside now, per gate, who's on duty, feedback as it lands. Next morning: one report to forward to the committee.</p></div>
            <div class="stage"><div class="rounded-2xl bg-[var(--ink)] p-5 text-white" style="min-width:230px"><div class="text-[11px] text-neutral-400">Inside now</div><div class="num text-5xl font-extrabold leading-none">1,099</div><div class="mt-3 h-2 rounded-full bg-neutral-800"><div class="h-full w-[73%] rounded-full bg-[var(--coral)]"></div></div><div class="mt-1 text-[11px] text-neutral-400">73% of 1,500 · peak 9:15 PM</div></div></div>
        </article>
    </div>
</section>

{{-- ===== Dashboard bento ===== --}}
<section id="dashboard" class="bg-white">
    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
        <div class="max-w-2xl rise"><h2 class="h2">The whole event on one screen.</h2><p class="mt-4 text-lg text-neutral-600">Numbers from a real demo event, drawn live as you scroll.</p></div>
        <div id="bento" class="bento mt-10">
            <div class="tile t-gauge reveal">
                <h3>Inside now</h3>
                <svg class="gauge" viewBox="0 0 200 120" aria-hidden="true"><path class="track" d="M20 110 A80 80 0 0 1 180 110"/><path class="arc" data-pct="73" d="M20 110 A80 80 0 0 1 180 110"/></svg>
                <div class="-mt-10 text-center"><div class="num text-5xl font-extrabold leading-none" style="font-stretch:85%" data-count="1099">0</div><div class="mt-1 text-sm text-neutral-500">73% of 1,500 capacity · people, not scans</div></div>
                <div class="mt-5 grid grid-cols-2 gap-3 text-center text-sm"><div class="rounded-xl bg-[var(--stone)] p-3"><div class="num text-xl font-extrabold" data-count="1120">0</div><div class="text-neutral-500">checked in</div></div><div class="rounded-xl bg-[var(--stone)] p-3"><div class="num text-xl font-extrabold" data-count="1500">0</div><div class="text-neutral-500">registered</div></div></div>
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
                <div class="duty"><span class="text-neutral-500">Jay · Stage</span><span class="text-xs font-bold text-[var(--coral)]">not arrived</span></div>
            </div>
            <div class="tile t-scans reveal">
                <h3>Entry scans</h3>
                <div class="num big" data-count="1248">0</div>
                <div class="mt-2 text-sm text-white/70"><b class="num" data-count="24">0</b> flagged as duplicates, none blocked</div>
            </div>
            <div class="tile t-feedback reveal">
                <h3>Feedback · 4.1 ★ from 100</h3>
                <svg class="spark" viewBox="0 0 300 70" preserveAspectRatio="none" aria-hidden="true"><path class="a" d="M0 60 L30 52 L60 55 L90 40 L120 44 L150 30 L180 34 L210 22 L240 26 L270 14 L300 18 V70 H0 Z"/><path class="l" d="M0 60 L30 52 L60 55 L90 40 L120 44 L150 30 L180 34 L210 22 L240 26 L270 14 L300 18"/></svg>
                <div class="mt-2"><div class="hbar"><span>5 ★</span><i data-w="46"></i><span class="num">46</span></div><div class="hbar"><span>4 ★</span><i data-w="31"></i><span class="num">31</span></div><div class="hbar"><span>3 ★ or less</span><i data-w="23"></i><span class="num">23</span></div></div>
            </div>
            <div class="tile t-report reveal">
                <h3>Post-event report</h3>
                <div class="report-sheet"><b>Sharad Utsav Garba 2026</b><br>1,120 attended · 75% showed up<br>Peak 9:15 PM · 194 in 15 min<br>Gates: G1 71% · G2 25% · G3 4%<br>Feedback 4.1 ★ · 6 stalls · 139 leads</div>
                <div class="mt-3 text-center text-xs text-neutral-500">Print, save as PDF, plus attendees CSV and vendor lead lists</div>
            </div>
            <div class="tile t-draw reveal">
                <h3>Lucky draw</h3>
                <div class="text-sm text-white/85">Winner picked from people <b>inside right now</b>. Seed fingerprint published before the draw.</div>
                <div class="mt-3 rounded-xl bg-black/25 p-3"><div class="text-[10px] text-white/60">Mixer grinder</div><div class="text-lg font-extrabold">Kunal B. · 98xxxx0005</div></div>
            </div>
        </div>
    </div>
</section>

{{-- ===== Principles as pass stubs ===== --}}
<section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
    <h2 class="h2 rise max-w-3xl">Built for a ground with bad Wi-Fi and a committee with no budget.</h2>
    <div class="stubs mt-10">
        <div class="stub" tabindex="0"><span class="notch"></span>
            <div class="top"><div class="s1"><div class="chip">📷 Phone camera</div></div><div class="s2"><div class="chip" style="border:1.5px dashed var(--ink)">🎟 Pass on their phone</div></div></div>
            <div class="body"><div class="text-lg font-extrabold">No app, no account</div><p class="mt-1 text-[15px] text-neutral-600">Attendees scan with the camera they already have. If it fails, the volunteer types the 8-letter code.</p></div>
        </div>
        <div class="stub" tabindex="0"><span class="notch"></span>
            <div class="top"><div class="s1"><div class="chip"><span class="wifi-off">📶</span> Wi-Fi died · 3 scans queued</div></div><div class="s2"><div class="chip" style="color:var(--ok)">✓ Back online · 3 synced</div></div></div>
            <div class="body"><div class="text-lg font-extrabold">Keeps working offline</div><p class="mt-1 text-[15px] text-neutral-600">Scanners cache the list before doors open. Scans queue on the phone and sync when the signal is back.</p></div>
        </div>
        <div class="stub" tabindex="0"><span class="notch"></span>
            <div class="top"><div class="s1"><div class="chip">Society garba · once a year</div></div><div class="s2"><div class="chip"><b>₹0</b> /month · pay per event</div></div></div>
            <div class="body"><div class="text-lg font-extrabold">Pay only for events you run</div><p class="mt-1 text-[15px] text-neutral-600">Free under 300 attendees. A flat price per event above that. No subscription for one night a year.</p></div>
        </div>
        <div class="stub" tabindex="0"><span class="notch"></span>
            <div class="top"><div class="s1"><div class="chip">Scan at Gate 2…</div></div><div class="s2"><div class="chip"><span class="live-dot"></span> 1,100 inside · 3 s later</div></div></div>
            <div class="body"><div class="text-lg font-extrabold">Live, not "refresh and wait"</div><p class="mt-1 text-[15px] text-neutral-600">Dashboard and gate board update every few seconds. A stale headcount is worse than none.</p></div>
        </div>
    </div>
</section>

{{-- ===== Use cases: one scene, a switch ===== --}}
<section id="who" class="bg-white">
    <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <h2 class="h2 rise max-w-2xl">Made for the events that don't have a ticketing budget.</h2>
            <div id="uc-seg" class="seg" role="tablist" aria-label="Event type">
                <button role="tab" data-key="fair" aria-selected="true">Fair / mela</button>
                <button role="tab" data-key="fest" aria-selected="false">College fest</button>
                <button role="tab" data-key="sport" aria-selected="false">Tournament</button>
                <button role="tab" data-key="temple" aria-selected="false">Religious gathering</button>
            </div>
        </div>
        <div class="scene-wrap mt-8">
            <div class="scene relative">
                @foreach ([
                    ['fair', 'Fair at night with lit Ferris wheels and a crowd', 'Main Gate', 'Kulfi stall', '1,120 inside'],
                    ['fest', 'Outdoor college fest stage with a large student crowd', 'Gate A', 'Stage zone', '2,800 inside'],
                    ['sport', 'Cricket match on a local ground', 'Ground 1 gate', 'Scorer table', '640 in the stands'],
                    ['temple', 'Illuminated temple tower with devotees gathered at night', 'Entry queue', 'Prasad counter', '1,500 capacity'],
                ] as [$key, $alt, $m1, $m2, $chip])
                    <figure data-view="{{ $key }}" class="{{ $key === 'fair' ? 'on' : '' }} relative m-0">
                        <img src="/landing/photos/{{ $key }}.jpg" alt="{{ $alt }}" width="1600" height="900" loading="lazy" decoding="async" class="block aspect-[16/9] w-full object-cover">
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/55 via-black/5 to-transparent"></div>
                        <span class="pin" style="left:18%;top:62%"><i></i>{{ $m1 }}</span>
                        <span class="pin coral" style="left:66%;top:48%"><i></i>{{ $m2 }}</span>
                        <figcaption class="absolute bottom-4 left-4 flex items-center gap-2 rounded-full bg-black/60 px-3 py-1.5 text-xs font-bold text-white backdrop-blur"><span class="live-dot"></span>{{ $chip }}</figcaption>
                    </figure>
                @endforeach
            </div>
            <div id="uc-facts" class="facts">
                <div class="fact"><span class="v num">1,120</span><span class="l text-neutral-600">inside at peak</span></div>
                <div class="fact"><span class="v num">7</span><span class="l text-neutral-600">volunteers on duty</span></div>
                <div class="fact"><span class="v num">139</span><span class="l text-neutral-600">stall leads captured</span></div>
                <p class="px-1 pt-2 text-sm text-neutral-500">Same kit, same scanner, same report. Only the ground plan changes.</p>
            </div>
        </div>
    </div>
</section>

{{-- ===== Final CTA ===== --}}
<section class="cta relative overflow-hidden bg-[var(--plum-deep)] text-white">
    <img src="/landing/photos/cta.jpg" alt="" width="1600" height="900" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover opacity-40">
    <div class="absolute inset-0 bg-gradient-to-b from-[var(--plum-deep)]/70 via-[var(--plum-deep)]/40 to-[var(--plum-deep)]/85"></div>
    <div class="relative mx-auto max-w-7xl px-5 py-24 sm:px-8 lg:py-36">
        <div class="relative mx-auto max-w-3xl text-center">
            <span class="crop tl"></span><span class="crop tr"></span><span class="crop bl"></span><span class="crop br"></span>
            <h2 class="h2">Run your next event without the clipboard.</h2>
            <p class="mx-auto mt-5 max-w-xl text-lg text-white/70">We create the event with you, you print the kit and forward one link. That's the setup.</p>
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ $wa }}" class="btn btn-white">Start your event on WhatsApp</a>
                <a href="/demo" class="btn btn-outline-white">Open the live demo</a>
            </div>
            <p class="mt-6 text-sm text-white/60">Message us, we set the event up with you the same day. No sign-up form.</p>
        </div>
    </div>
</section>

<footer class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-4 px-5 py-8 text-sm text-neutral-500 sm:flex-row sm:items-center sm:px-8">
    <div class="flex items-center gap-2"><img src="/brand/mark.png" alt="" class="h-6 w-6"><span>Gatezo, by Infrion Technolab, Ahmedabad</span></div>
    <div class="flex gap-6"><a href="/admin/login" class="hover:text-[var(--ink)]">Sign in</a><a href="/scan" class="hover:text-[var(--ink)]">Volunteer scanner</a></div>
</footer>
</body>
</html>
