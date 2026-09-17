@extends('layouts.public', ['title' => 'Waiting for approval', 'hideHeader' => true])
@section('content')
    <meta http-equiv="refresh" content="10">
    <div class="my-auto text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-3xl">⏳</div>
        <h1 class="mt-5 text-2xl font-bold">Almost in</h1>
        <p class="mt-2 text-neutral-600">{{ $volunteer->name }}, the organizer of <b>{{ $event->name }}</b> has to approve you before the scanner opens. Ask them to tap Approve on the Volunteers page.</p>
        <p class="mt-6 text-xs text-neutral-400">This page checks again every 10 seconds.</p>
        <form method="post" action="{{ route('scan.leave') }}" class="mt-8">@csrf<button class="text-sm underline">Not you? Leave</button></form>
    </div>
@endsection
