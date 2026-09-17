<?php

use App\Http\Middleware\EnsureCurrentVendorLink;
use App\Http\Middleware\EnsureVolunteerSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Caddy (compose.prod.yml) or Cloudflare the app must trust X-Forwarded-* so
        // signed URLs and asset links come out https. Set TRUSTED_PROXIES='*' there.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Plain `auth` routes (print kit, report, CSV) send guests to the Filament login.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));

        $middleware->alias([
            'volunteer' => EnsureVolunteerSession::class,
            'vendor.current' => EnsureCurrentVendorLink::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
