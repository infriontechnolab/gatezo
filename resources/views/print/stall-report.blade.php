@extends('layouts.print', ['title' => $stall->name.' · stall report'])
@section('content')
<section class="sheet">
    <div style="display:flex;align-items:center;justify-content:space-between"><img class="brand" src="{{ asset('brand/logo.png') }}" alt="Gatezo"><span class="hint">Stall report</span></div>
    <div class="stripe" style="margin-top:4mm"></div>
    <div class="kicker" style="margin-top:8mm">{{ $event->name }}</div>
    <div class="title" style="font-size:34px">{{ $stall->name }}</div>
    <div style="color:#555">@if($stall->location){{ $stall->location }} · @endif @if($event->starts_at){{ $event->starts_at->format('D, j M Y') }} · @endif{{ $event->venue }} · generated {{ now()->format('j M Y, g:i A') }}</div>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:4mm;margin-top:8mm">
        <div class="stat"><b>{{ number_format($stall->view_count) }}</b><span>stall page opens</span></div>
        <div class="stat"><b>{{ number_format($leads) }}</b><span>leads · {{ $stall->view_count ? round($leads / $stall->view_count * 100) : 0 }}% of page opens</span></div>
        <div class="stat"><b>{{ number_format($attended) }}</b><span>people at the event · {{ $attended ? round($leads / $attended * 100) : 0 }}% became leads</span></div>
        <div class="stat"><b>#{{ $rank }}</b><span>of {{ $stallCount }} stalls by page opens</span></div>
    </div>

    <h2>Leads by hour</h2>
    @if ($hours->count())
        @php $max = max(1, $hours->max('n')); @endphp
        <table>
            @foreach ($hours as $h)
            <tr><td style="width:90px;white-space:nowrap">{{ \Carbon\Carbon::parse($h['at'])->format('g A') }}</td><td><div style="background:var(--accent);height:10px;width:{{ round($h['n'] / $max * 100) }}%;min-width:2px"></div></td><td style="width:60px">{{ $h['n'] }}</td></tr>
            @endforeach
        </table>
        <div class="hint" style="margin-top:2mm">Busiest hour: {{ \Carbon\Carbon::parse($peak['at'])->format('g A') }} ({{ $peak['n'] }} leads)</div>
    @else
        <div class="hint">No leads captured.</div>
    @endif

    <h2>How these are counted</h2>
    <table>
        <tr><td style="width:160px">Stall page opens</td><td>Every time a visitor scanned the stall card and opened its page. One person opening it twice counts twice.</td></tr>
        <tr><td>Leads</td><td>Visitors whose pass the stall scanned, who had agreed on their pass to share their contact. Each person counts once.</td></tr>
        <tr><td>People at the event</td><td>Unique passes scanned in at the gates.</td></tr>
        @if ($feedbackAvg)
        <tr><td>Event rating</td><td>{{ $feedbackAvg }} ★ from attendee feedback</td></tr>
        @endif
    </table>
    <div class="hint" style="margin-top:4mm">Contact details of the leads are in the stall's private link (Leads CSV), not in this report.</div>
</section>
@endsection
