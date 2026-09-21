@extends('layouts.print', ['title' => 'Print kit'])
@php
    $style = in_array($event->kit_style, ['classic', 'bold', 'festival'], true) ? $event->kit_style : 'bold';
    $logo = $event->logo_url ? Illuminate\Support\Facades\Storage::url($event->logo_url) : null;
    $when = $event->starts_at?->format('l, j F · g:i A');
@endphp
@push('head')
<style>
    /* ---- Kit templates. One body class switches the whole kit. Everything is CSS so a browser
       print → PDF is the only pipeline; "Background graphics" must be on for bold/festival. ---- */
    .kit .sheet { position: relative; overflow: hidden; }
    .k-head { display: flex; flex-direction: column; align-items: center; text-align: center; }
    .k-title { font-weight: 800; letter-spacing: -.02em; line-height: 1.02; }
    .k-when { font-size: 17px; margin-top: 4mm; }
    .k-qr { background: #fff; border-radius: 8mm; padding: 8mm; }
    .k-qr svg { display: block; width: 100%; height: auto; }
    .k-foot { margin-top: auto; display: flex; align-items: center; justify-content: space-between; gap: 6mm; }
    .k-logo { max-height: 14mm; max-width: 50mm; object-fit: contain; }
    .k-pill { display: inline-block; padding: 2.5mm 6mm; border-radius: 999px; font-weight: 700; font-size: 14px; }
    .k-note { font-size: 13px; line-height: 1.4; }
    .k-url { font-family: ui-monospace, Menlo, monospace; font-size: 12px; word-break: break-all; }
    .k-vol { background: repeating-linear-gradient(45deg, #111 0 8px, #fff 8px 16px); height: 6mm; }
    .k-corner { position: absolute; width: 10mm; height: 10mm; border: 2.5px solid currentColor; opacity: .5; }
    .k-corner.tl { left: 10mm; top: 10mm; border-right: 0; border-bottom: 0; } .k-corner.tr { right: 10mm; top: 10mm; border-left: 0; border-bottom: 0; }
    .k-corner.bl { left: 10mm; bottom: 10mm; border-right: 0; border-top: 0; } .k-corner.br { right: 10mm; bottom: 10mm; border-left: 0; border-top: 0; }

    /* Classic: black on white, accent only as a thin rule. */
    .kit-classic .k-band { border-top: 2mm solid var(--accent); border-bottom: 2mm solid var(--accent); padding: 8mm 0; }
    .kit-classic .k-qr { border: 1.5px solid #111; }
    .kit-classic .k-pill { border: 1.5px solid #111; }
    .kit-classic .card { border: 1.5px dashed #999; }

    /* Bold: accent header and footer bands, white body, rounded QR frame with a hairline. */
    .kit-bold .sheet.poster, .kit-bold .sheet.gate { padding: 0; }
    .kit-bold .k-band { background: var(--accent); color: #fff; padding: 14mm 18mm 12mm; }
    .kit-bold .k-band.dark { background: #111; }
    .kit-bold .k-band .kicker { color: rgba(255,255,255,.8); }
    .kit-bold .k-body { padding: 12mm 18mm 0; display: flex; flex-direction: column; align-items: center; flex: 1; }
    .kit-bold .k-qr { box-shadow: 0 0 0 1.5px #111, 0 6mm 10mm -6mm rgba(0,0,0,.4); }
    .kit-bold .k-footband { background: #111; color: #fff; padding: 8mm 18mm; margin-top: auto; }
    .kit-bold .k-pill { background: #111; color: #fff; }
    .kit-bold .card { border: 0; box-shadow: 0 0 0 1.5px #ddd; border-top: 4mm solid var(--accent); }
    .kit-bold .card.exit { border-top-color: #111; }

    /* Festival: full accent page, white card floating on it, corner marks. */
    .kit-festival .sheet.poster, .kit-festival .sheet.gate { background: var(--accent); padding: 16mm; color: #fff; }
    .kit-festival .sheet.gate { background: #111; }
    .kit-festival .k-card { background: #fff; color: #111; border-radius: 10mm; padding: 14mm 14mm 12mm; flex: 1; display: flex; flex-direction: column; align-items: center; text-align: center; }
    .kit-festival .k-qr { box-shadow: 0 0 0 1.5px #111; }
    .kit-festival .k-pill { background: var(--accent); color: #fff; }
    .kit-festival .sheet.gate .k-pill { background: #111; }
    .kit-festival .card { border: 0; background: #fff; box-shadow: 0 0 0 1.5px #ddd; }
    .kit-festival .sheet.card-grid { background: var(--accent); padding: 12mm; }
    .kit-festival .sheet.card-grid.exit { background: #111; }
</style>
@endpush
@section('content')
<div class="kit kit-{{ $style }}">

{{-- 1. Registration poster (A4). Stick at the entrance, on the notice board, forward on WhatsApp. --}}
<section class="sheet poster">
    @if ($style === 'festival')
        <span class="k-corner tl"></span><span class="k-corner tr"></span><span class="k-corner bl"></span><span class="k-corner br"></span>
        <div class="k-card">
            @if ($logo)<img class="k-logo" src="{{ $logo }}" alt="">@endif
            <div class="kicker" style="margin-top:6mm">Scan to get your entry pass</div>
            <div class="k-title" style="font-size:46px;margin-top:3mm">{{ $event->name }}</div>
            @if ($when || $event->venue)<div class="k-when" style="color:#555">{{ $when }}@if($when && $event->venue) · @endif{{ $event->venue }}</div>@endif
            <div class="k-qr" style="width:110mm;margin:10mm 0 8mm">{!! $posterQr !!}</div>
            <span class="k-pill">Free entry · No app · 20 seconds</span>
            <div class="k-note hint" style="margin-top:6mm">Or open <span class="k-url">{{ route('event.show', $event) }}</span></div>
            <div class="k-foot" style="width:100%;padding-top:8mm"><img class="brand" src="{{ asset('brand/logo.png') }}" alt="Gatezo"><span class="hint">Entry pass on your phone · show it at the gate</span></div>
        </div>
    @elseif ($style === 'bold')
        <div class="k-band k-head">
            @if ($logo)<img class="k-logo" src="{{ $logo }}" alt="" style="margin-bottom:5mm">@endif
            <div class="kicker">Scan to get your entry pass</div>
            <div class="k-title" style="font-size:44px;margin-top:3mm">{{ $event->name }}</div>
            @if ($when || $event->venue)<div class="k-when" style="opacity:.9">{{ $when }}@if($when && $event->venue) · @endif{{ $event->venue }}</div>@endif
        </div>
        <div class="k-body">
            <div class="k-qr" style="width:112mm;margin-top:6mm">{!! $posterQr !!}</div>
            <span class="k-pill" style="margin-top:9mm">Free entry · No app · 20 seconds</span>
            <div class="k-note hint" style="margin-top:5mm;text-align:center">Point your phone camera at the code.<br>Or open <span class="k-url">{{ route('event.show', $event) }}</span></div>
        </div>
        <div class="k-footband k-foot"><img class="brand" src="{{ asset('brand/logo.png') }}" alt="Gatezo" style="filter:brightness(0) invert(1);opacity:1"><span style="font-size:13px;opacity:.85">Entry pass on your phone · show it at the gate</span></div>
    @else
        <div class="k-band k-head">
            @if ($logo)<img class="k-logo" src="{{ $logo }}" alt="" style="margin-bottom:5mm">@endif
            <div class="kicker">Scan to get your entry pass</div>
            <div class="k-title" style="font-size:44px;margin-top:3mm">{{ $event->name }}</div>
            @if ($when || $event->venue)<div class="k-when" style="color:#555">{{ $when }}@if($when && $event->venue) · @endif{{ $event->venue }}</div>@endif
        </div>
        <div class="k-head" style="flex:1">
            <div class="k-qr" style="width:112mm;margin-top:12mm">{!! $posterQr !!}</div>
            <span class="k-pill" style="margin-top:9mm">Free entry · No app · 20 seconds</span>
            <div class="k-note hint" style="margin-top:5mm">Or open <span class="k-url">{{ route('event.show', $event) }}</span></div>
        </div>
        <div class="k-foot"><img class="brand" src="{{ asset('brand/logo.png') }}" alt="Gatezo"><span class="hint">Entry pass on your phone · show it at the gate</span></div>
    @endif
</section>

{{-- 2. One A4 sign per gate/zone. Striped = volunteer-facing, so nobody scans it expecting a pass. --}}
@foreach ($event->gates as $gate)
<section class="sheet gate">
    @if ($style === 'festival')
        <span class="k-corner tl"></span><span class="k-corner tr"></span><span class="k-corner bl"></span><span class="k-corner br"></span>
        <div class="k-card">
            <div class="k-vol" style="width:100%;border-radius:3mm"></div>
            <div class="kicker" style="margin-top:8mm">Volunteers only · scan when you arrive</div>
            <div class="k-title" style="font-size:60px;margin-top:3mm">{{ $gate->name }}</div>
            <div class="k-when" style="color:#555">{{ $gate->is_entry ? 'Entry gate' : 'Duty zone' }} · {{ $event->name }}</div>
            <div class="k-qr" style="width:96mm;margin:10mm 0 6mm">{!! $gateQrs[$gate->id] !!}</div>
            <span class="k-pill" style="font-family:ui-monospace,Menlo,monospace;letter-spacing:.2em;font-size:22px">{{ $gate->code }}</span>
            <div class="k-note hint" style="margin-top:8mm">If the camera fails: open <span class="k-url">{{ route('scan.join') }}</span>, join with the event code, pick "{{ $gate->name }}".</div>
            <div class="k-vol" style="width:100%;border-radius:3mm;margin-top:auto"></div>
        </div>
    @elseif ($style === 'bold')
        <div class="k-band dark k-head">
            <div class="k-vol" style="width:100%;border-radius:2mm;margin-bottom:8mm"></div>
            <div class="kicker">Volunteers only · scan when you arrive</div>
            <div class="k-title" style="font-size:60px;margin-top:3mm">{{ $gate->name }}</div>
            <div class="k-when" style="opacity:.8">{{ $gate->is_entry ? 'Entry gate' : 'Duty zone' }} · {{ $event->name }}</div>
        </div>
        <div class="k-body">
            <div class="k-qr" style="width:96mm;margin-top:8mm">{!! $gateQrs[$gate->id] !!}</div>
            <span class="k-pill" style="margin-top:9mm;font-family:ui-monospace,Menlo,monospace;letter-spacing:.2em;font-size:22px;background:var(--accent)">{{ $gate->code }}</span>
            <div class="k-note hint" style="margin-top:6mm;text-align:center">If the camera fails: open <span class="k-url">{{ route('scan.join') }}</span>,<br>join with the event code, pick "{{ $gate->name }}".</div>
        </div>
        <div class="k-footband"><div class="k-vol" style="border-radius:2mm"></div></div>
    @else
        <div class="k-vol"></div>
        <div class="k-head" style="flex:1">
            <div class="kicker" style="margin-top:12mm">Volunteers only · scan when you arrive</div>
            <div class="k-title" style="font-size:60px;margin-top:3mm">{{ $gate->name }}</div>
            <div class="k-when" style="color:#555">{{ $gate->is_entry ? 'Entry gate' : 'Duty zone' }} · {{ $event->name }}</div>
            <div class="k-qr" style="width:96mm;margin:12mm 0 6mm">{!! $gateQrs[$gate->id] !!}</div>
            <div class="code" style="font-size:40px">{{ $gate->code }}</div>
            <div class="k-note hint" style="margin-top:6mm">If the camera fails: open <span class="k-url">{{ route('scan.join') }}</span>, join with the event code, pick "{{ $gate->name }}".</div>
        </div>
        <div class="k-vol" style="margin-top:auto"></div>
    @endif
</section>
@endforeach

{{-- 3. Stall cards, 4 per A4. Attendee scans → menu, offers, location. --}}
@foreach ($event->stalls->chunk(4) as $chunk)
<section class="sheet card-grid">
    @foreach ($chunk as $stall)
    <div class="card">
        <div class="kicker">Scan for menu &amp; offers</div>
        <div class="title" style="font-size:24px">{{ $stall->name }}</div>
        @if ($stall->location)<div class="hint">{{ $stall->location }}</div>@endif
        <div class="qr" style="width:55mm;margin:4mm 0">{!! $stallQrs[$stall->id] !!}</div>
        @if ($stall->offers)<span class="k-pill" style="font-size:12px;padding:2mm 4mm">{{ \Illuminate\Support\Str::limit($stall->offers, 60) }}</span>@endif
        <div class="hint" style="margin-top:auto">{{ $event->name }}</div>
    </div>
    @endforeach
    @for ($i = $chunk->count(); $i < 4; $i++)
    <div class="card">
        <div class="kicker">Scan for menu &amp; offers</div>
        <div class="title" style="font-size:24px;color:#bbb">Stall name</div>
        <div class="write"></div>
        <div class="hint" style="margin-top:auto">Blank card: add the stall in the panel to get its QR</div>
    </div>
    @endfor
</section>
@endforeach

{{-- 4. Exit feedback cards, 2 per A4. --}}
<section class="sheet card-grid exit" style="grid-template-columns:1fr">
    @for ($i = 0; $i < 2; $i++)
    <div class="card exit" style="justify-content:center">
        <div class="kicker">On your way out</div>
        <div class="title" style="font-size:36px">How was it?</div>
        <div class="hint" style="font-size:15px">10 seconds. Anonymous. The organizers read every one.</div>
        <div class="qr" style="width:70mm;margin:6mm 0">{!! $feedbackQr !!}</div>
        <div class="hint">{{ $event->name }}</div>
    </div>
    @endfor
</section>

</div>
@endsection
