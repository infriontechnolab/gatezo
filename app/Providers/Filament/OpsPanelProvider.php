<?php

namespace App\Providers\Filament;

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
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
            ->login()
            ->brandName('Gatezo Ops')
            ->brandLogo(asset('brand/logo.png'))
            ->darkModeBrandLogo(asset('brand/logo.png'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('brand/mark.png'))
            ->font('Inter')
            ->colors([
                'primary' => Color::hex('#4A3B6B'), // plum, so nobody mistakes it for the organizer panel
                'success' => Color::hex('#4A9B5E'),
                'warning' => Color::hex('#D9713C'),
                'danger' => Color::hex('#B23A48'),
                'gray' => Color::hex('#78716C'),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
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
