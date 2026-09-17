@extends('layouts.print', ['title' => 'Print kit'])
@section('content')
{{-- 1. Registration poster (A4). Stick at the entrance, on the notice board, forward on WhatsApp. --}}
<section class="sheet" style="align-items:center;text-align:center;justify-content:center">
    <div class="stripe" style="width:100%"></div>
    <div class="kicker" style="margin-top:12mm">Scan to get your entry pass</div>
    <div class="title" style="font-size:44px">{{ $event->name }}</div>
    @if ($event->starts_at)<div style="font-size:18px;color:#555">{{ $event->starts_at->format('l, j F · g:i A') }}@if($event->venue) · {{ $event->venue }}@endif</div>@endif
    <div class="qr" style="width:120mm;margin:12mm 0">{!! $posterQr !!}</div>
    <div style="font-size:16px;color:#555">Free entry · No app needed · Takes 20 seconds</div>
    <div class="hint" style="margin-top:6mm">Or open: <b>{{ route('event.show', $event) }}</b></div>
    <div style="margin-top:auto;width:100%;display:flex;align-items:center;justify-content:space-between"><img class="brand" src="{{ asset('brand/logo-light.png') }}" alt="Gatezo"><span class="hint">Free entry pass · no app needed</span></div>
    <div class="stripe" style="width:100%;margin-top:4mm"></div>
</section>

{{-- 2. One A4 sign per gate/zone. Striped = volunteer-facing, so nobody scans it expecting a pass. --}}
@foreach ($event->gates as $gate)
<section class="sheet" style="align-items:center;text-align:center;justify-content:center">
    <div class="stripe volunteer" style="width:100%"></div>
    <div class="kicker" style="margin-top:12mm">Volunteers only · scan when you arrive</div>
    <div class="title" style="font-size:56px">{{ $gate->name }}</div>
    <div style="font-size:18px;color:#555">{{ $gate->is_entry ? 'Entry gate' : 'Duty zone' }} · {{ $event->name }}</div>
    <div class="qr" style="width:100mm;margin:12mm 0">{!! $gateQrs[$gate->id] !!}</div>
    <div class="code" style="font-size:40px">{{ $gate->code }}</div>
    <div class="hint">If the camera fails: open <b>{{ route('scan.join') }}</b>, join with the event code, pick "{{ $gate->name }}".</div>
    <div class="stripe volunteer" style="width:100%;margin-top:auto"></div>
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
        @if ($stall->offers)<div style="font-weight:600;font-size:13px">{{ \Illuminate\Support\Str::limit($stall->offers, 80) }}</div>@endif
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
<section class="sheet card-grid" style="grid-template-columns:1fr">
    @for ($i = 0; $i < 2; $i++)
    <div class="card" style="justify-content:center">
        <div class="kicker">On your way out</div>
        <div class="title" style="font-size:36px">How was it?</div>
        <div class="hint" style="font-size:15px">10 seconds. Anonymous. The organizers read every one.</div>
        <div class="qr" style="width:70mm;margin:6mm 0">{!! $feedbackQr !!}</div>
        <div class="hint">{{ $event->name }}</div>
    </div>
    @endfor
</section>
@endsection
