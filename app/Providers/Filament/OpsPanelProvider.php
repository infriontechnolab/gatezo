<?php

namespace App\Providers\Filament;

use App\Filament\Auth\OpsLogin;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Filament\Ops\Widgets\Overview;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Our panel at /ops: every organizer, every event, plans, and "log in as" to see what a
 * client sees. No tenancy. Only users with is_admin get past login (User::canAccessPanel).
 */
class OpsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('ops')
            ->path('ops')
            ->login(OpsLogin::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->brandName('Gatezo Ops')
            ->brandLogo(asset('brand/logo.png'))
            ->darkModeBrandLogo(asset('brand/logo.png'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('brand/mark.png'))
            ->font('Inter')
            ->colors([
                // Brand plum, so nobody mistakes it for the coral organizer panel. Spelled out because
                // Color::hex() treats #6B2D5C as a 900 and makes buttons (600) a bright magenta.
                'primary' => [
                    50 => 'oklch(0.975 0.012 343)', 100 => 'oklch(0.945 0.027 343)', 200 => 'oklch(0.89 0.05 343)',
                    300 => 'oklch(0.8 0.08 343)', 400 => 'oklch(0.66 0.11 343)', 500 => 'oklch(0.53 0.12 343)',
                    600 => 'oklch(0.405 0.105 343)', 700 => 'oklch(0.355 0.093 343)', 800 => 'oklch(0.31 0.08 343)',
                    900 => 'oklch(0.27 0.066 343)', 950 => 'oklch(0.2 0.05 343)',
                ],
                'success' => Color::hex('#4A9B5E'),
                'warning' => Color::hex('#D9713C'),
                'danger' => Color::hex('#B23A48'),
                'gray' => Color::hex('#78716C'),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Display face for headings, same as the landing page.
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => new HtmlString('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&display=swap" rel="stylesheet">'))
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Ops/Resources'), for: 'App\Filament\Ops\Resources')
            ->pages([Dashboard::class])
            ->widgets([Overview::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
