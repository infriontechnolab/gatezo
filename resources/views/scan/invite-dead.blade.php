@extends('layouts.public', ['title' => 'Volunteer scanner'])
@section('content')
    <h1 class="text-2xl font-bold">This link doesn't work</h1>
    <p class="mt-1 text-neutral-500">{{ $event->name }}</p>
    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">{{ $reason }}</div>
    @if ($event->join_by_code)
        <p class="mt-6 text-sm text-neutral-600">Have the event code instead? <a href="{{ route('scan.join') }}" class="font-semibold underline">Join with the code</a>.</p>
    @endif
@endsection
