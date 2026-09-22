@extends('layouts.public')
@section('content')
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-neutral-200">
        <div class="flex items-center gap-4">
            @if ($stall->logo_url)
                <img src="{{ $stall->logoUrl() }}" alt="" class="h-16 w-16 rounded-xl object-cover">
            @endif
            <div>
                <h1 class="text-xl font-bold">{{ $stall->name }}</h1>
                @if ($stall->location)<div class="text-sm text-neutral-500">{{ $stall->location }}</div>@endif
            </div>
        </div>
        @if ($stall->description)<p class="mt-4 text-neutral-700">{{ $stall->description }}</p>@endif
        @if ($stall->offers)
            <div class="mt-4 rounded-xl p-4 text-white" style="background: var(--accent)">
                <div class="text-xs font-semibold uppercase tracking-wider opacity-80">Offer</div>
                <div class="mt-1 font-medium">{{ $stall->offers }}</div>
            </div>
        @endif
        @if ($stall->products)
            <h2 class="mt-6 text-sm font-semibold uppercase tracking-wider text-neutral-400">Menu</h2>
            <ul class="mt-2 divide-y divide-neutral-100">
                @foreach ($stall->products as $p)
                    <li class="flex items-baseline justify-between py-2.5">
                        <div>
                            <div class="font-medium">{{ $p['name'] ?? '' }}</div>
                            @if (! empty($p['note']))<div class="text-sm text-neutral-500">{{ $p['note'] }}</div>@endif
                        </div>
                        @if (isset($p['price']))<div class="font-mono">₹{{ $p['price'] }}</div>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
