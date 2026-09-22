<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $event->accent_hex ?? '#E8604C' }}">
    <title>{{ $title ?? ($event->name ?? 'Gatezo') }}</title>
    {{-- WhatsApp preview when an organizer forwards the registration link. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Gatezo">
    <meta property="og:title" content="{{ $title ?? ($event->name ?? 'Gatezo') }}">
    <meta property="og:description" content="{{ isset($event) ? ($event->description ?: 'Scan to get your entry pass. Free entry, no app.') : 'Free local events, run from a phone.' }}">
    <meta property="og:image" content="{{ url('/og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    {{-- Passes, the scanner, vendor pages and boards are private links, not web pages: keep them
         out of search results even if someone posts one publicly. robots.txt says the same. --}}
    @if ($noindex ?? ! request()->is('e/*'))
        <meta name="robots" content="noindex, nofollow">
    @endif
    {{-- Per-surface manifest: a pass installs as "<event> pass" opening itself; the scanner as "Gatezo scanner". --}}
    @php $manifestStart = $manifestStart ?? (request()->is('pass/*') ? '/'.request()->path() : '/scan'); @endphp
    <link rel="manifest" href="{{ route('manifest', array_filter(['start' => $manifestStart, 'event' => $event->slug ?? null])) }}">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" href="/brand/mark.png" type="image/png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ request()->is('pass/*') ? 'Pass' : 'Gatezo' }}">
    <style>:root { --accent: {{ $event->accent_hex ?? '#E8604C' }}; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-dvh bg-neutral-50 text-neutral-900 antialiased">
    <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col px-5 py-8">
        @if (isset($event) && empty($hideHeader))
            <header class="mb-6 flex items-center gap-3">
                @if ($event->logo_url)
                    <img src="{{ $event->logoUrl() }}" alt="" class="h-10 w-10 rounded-lg object-cover">
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
        <footer class="mt-auto pt-10 text-center text-xs text-neutral-400">Powered by Gatezo</footer>
    </main>
    @stack('scripts')
</body>
</html>
