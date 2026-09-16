<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $event->accent_hex ?? '#E10600' }}">
    <title>{{ $title ?? ($event->name ?? 'EventQR') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <style>:root { --accent: {{ $event->accent_hex ?? '#E10600' }}; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-dvh bg-neutral-50 text-neutral-900 antialiased">
    <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col px-5 py-8">
        @if (isset($event))
            <header class="mb-6 flex items-center gap-3">
                @if ($event->logo_url)
                    <img src="{{ Illuminate\Support\Facades\Storage::url($event->logo_url) }}" alt="" class="h-10 w-10 rounded-lg object-cover">
                @else
                    <div class="h-10 w-10 rounded-lg" style="background: var(--accent)"></div>
                @endif
                <div class="leading-tight">
                    <div class="font-semibold">{{ $event->name }}</div>
                    @if ($event->venue)<div class="text-sm text-neutral-500">{{ $event->venue }}</div>@endif
                </div>
            </header>
        @endif
        {{ $slot ?? '' }}
        @yield('content')
        <footer class="mt-auto pt-10 text-center text-xs text-neutral-400">Powered by EventQR</footer>
    </main>
    @stack('scripts')
</body>
</html>
