@extends('layouts.print', ['title' => 'Post-event report'])
@section('content')
<section class="sheet">
    <div style="display:flex;align-items:center;justify-content:space-between"><img class="brand" src="{{ asset('brand/logo-light.png') }}" alt="Gatezo"><span class="hint">Post-event report</span></div>
    <div class="stripe" style="margin-top:4mm"></div>
    <div class="kicker" style="margin-top:8mm">Post-event report</div>
    <div class="title" style="font-size:34px">{{ $event->name }}</div>
    <div style="color:#555">@if($event->starts_at){{ $event->starts_at->format('D, j M Y') }} · @endif{{ $event->venue }} · generated {{ now()->format('j M Y, g:i A') }}</div>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:4mm;margin-top:8mm">
        <div class="stat"><b>{{ number_format($checkedIn) }}</b><span>attended (unique)</span></div>
        <div class="stat"><b>{{ number_format($registered) }}</b><span>registered · {{ $registered ? round($checkedIn / $registered * 100) : 0 }}% showed up</span></div>
        <div class="stat"><b>{{ $peak ? \Carbon\Carbon::parse($peak['at'])->format('g:i A') : '—' }}</b><span>peak arrival ({{ $peak['n'] ?? 0 }} in 15 min)</span></div>
        <div class="stat"><b>{{ $feedbackAvg ? $feedbackAvg.' ★' : '—' }}</b><span>{{ $feedbackCount }} feedback responses</span></div>
    </div>

    <h2>Arrivals (15-minute buckets)</h2>
    @if ($buckets->count())
        @php $max = max(1, $buckets->max('n')); @endphp
        <div class="bars">@foreach ($buckets as $b)<div style="height:{{ round($b['n'] / $max * 100) }}%" title="{{ $b['at'] }}: {{ $b['n'] }}"></div>@endforeach</div>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:#666;margin-top:2px"><span>{{ \Carbon\Carbon::parse($buckets->first()['at'])->format('g:i A') }}</span><span>{{ \Carbon\Carbon::parse($buckets->last()['at'])->format('g:i A') }}</span></div>
    @else
        <div class="hint">No check-ins recorded.</div>
    @endif

    <h2>Gates</h2>
    <table>
        <tr><th>Gate</th><th>Code</th><th>Check-ins</th><th>Share</th></tr>
        @foreach ($gates as $g)
        <tr><td>{{ $g->name }}</td><td>{{ $g->code }}</td><td>{{ $g->ins }}</td><td>{{ $totalScans ? round($g->ins / $totalScans * 100) : 0 }}%</td></tr>
        @endforeach
        <tr><td colspan="4" class="hint">{{ $totalScans }} entry scans total · {{ $duplicates }} flagged as duplicates · {{ $walkups }} walk-up registrations · {{ $volunteers }} volunteers on duty</td></tr>
    </table>

    <h2>Feedback</h2>
    <table>
        @foreach ($ratings as $r => $n)
        <tr><td style="width:60px">{{ $r }} ★</td><td><div style="background:var(--accent);height:10px;width:{{ $feedbackCount ? round($n / $feedbackCount * 100) : 0 }}%;min-width:{{ $n ? 2 : 0 }}px"></div></td><td style="width:60px">{{ $n }}</td></tr>
        @endforeach
    </table>
</section>

@if ($comments->count() || $stalls->count() || $shifts->count())
<section class="sheet">
    @if ($shifts->count())
    <h2 style="margin-top:0">Volunteer roster</h2>
    <table>
        <tr><th>Volunteer</th><th>Post</th><th>Shift</th><th>Outcome</th></tr>
        @foreach ($shifts as $s)
        <tr><td>{{ $s['name'] }}</td><td>{{ $s['post'] }}</td><td>{{ $s['window'] }}</td><td>{{ \App\Filament\Resources\Shifts\Tables\ShiftsTable::STATUS[$s['status']][0] ?? $s['status'] }}</td></tr>
        @endforeach
    </table>
    @endif

    @if ($comments->count())
    <h2 @if(!$shifts->count())style="margin-top:0"@endif>What people said</h2>
    <table>
        @foreach ($comments as $c)
        <tr><td style="width:60px;white-space:nowrap">{{ str_repeat('★', $c->rating) }}</td><td>{{ $c->comment }}</td></tr>
        @endforeach
    </table>
    @endif

    @if ($stalls->count())
    <h2>Stalls</h2>
    <table>
        <tr><th>Stall</th><th>Location</th><th>Page scans</th><th>Leads</th></tr>
        @foreach ($stalls as $s)
        <tr><td>{{ $s->name }}</td><td>{{ $s->location }}</td><td>{{ $s->view_count }}</td><td>{{ $s->leads_count }}</td></tr>
        @endforeach
    </table>
    @endif
</section>
@endif
@endsection
