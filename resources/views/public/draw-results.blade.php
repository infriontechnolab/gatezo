@extends('layouts.public', ['title' => 'Lucky draw results · '.$event->name])
@section('content')
    <h1 class="text-2xl font-bold">Lucky draw results</h1>
    <p class="mt-1 text-sm text-neutral-500">Every draw is decided by a secret seed whose hash is published before the draw runs, and revealed after. Same seed + same pool = same winners, for anyone who wants to check.</p>

    @forelse ($draws as $draw)
        <section class="mt-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-neutral-200">
            <div class="flex items-baseline justify-between">
                <h2 class="text-lg font-semibold">{{ $draw->name }}</h2>
                <span class="text-xs text-neutral-400">{{ $draw->run_at?->format('j M, g:i A') }} · {{ count($draw->pool_snapshot ?? []) }} in the pool</span>
            </div>
            <ul class="mt-3 divide-y divide-neutral-100">
                @foreach ($draw->prizes as $prize)
                    @foreach ($draw->winners->where('prize_id', $prize->id)->where('status', 'claimed')->sortBy('slot') as $w)
                        <li class="flex items-center justify-between py-2">
                            <span>{{ $prize->name }}{{ $prize->quantity > 1 ? ' #'.$w->slot : '' }}</span>
                            <span class="text-right"><span class="font-medium">{{ \App\Models\Draw::shortName($w->pass->attendee->name) }}</span>
                                @if (($draw->presentation['show_phone_masked'] ?? true) && $w->pass->attendee->phone)<span class="block text-xs text-neutral-400">{{ \App\Models\Draw::maskPhone($w->pass->attendee->phone) }}</span>@endif
                            </span>
                        </li>
                    @endforeach
                @endforeach
                @if ($draw->winners->where('status', 'claimed')->isEmpty())
                    <li class="py-2 text-sm text-neutral-400">{{ $draw->status === 'finished' ? 'No prize was claimed.' : 'In progress…' }}</li>
                @endif
            </ul>
            <details class="mt-3 text-xs text-neutral-500">
                <summary class="cursor-pointer">Proof</summary>
                <dl class="mt-2 space-y-1 break-all font-mono">
                    <div><dt class="font-sans text-neutral-400">seed hash (before)</dt><dd>{{ $draw->seed_hash }}</dd></div>
                    <div><dt class="font-sans text-neutral-400">seed (after)</dt><dd>{{ $draw->status === 'finished' ? $draw->getAttribute('seed') : 'revealed when the draw finishes' }}</dd></div>
                    <div><dt class="font-sans text-neutral-400">how to check</dt><dd class="font-sans">sha256(seed) must equal the hash. Order the pool by hmac_sha256(seed, pass code); prizes are filled in that order, winner then backups.</dd></div>
                </dl>
            </details>
        </section>
    @empty
        <p class="mt-6 text-neutral-500">No draws have been run yet.</p>
    @endforelse
@endsection
