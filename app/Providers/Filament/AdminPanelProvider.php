<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Tenancy\EditEventProfile;
use App\Filament\Pages\Tenancy\RegisterEvent;
use App\Models\Event;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Organizer panel at /admin. Each Event is a Filament tenant, so every
 * resource (gates, attendees, stalls, feedback...) is scoped to the event
 * picked in the sidebar switcher.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset()

            // ---- Look & feel -------------------------------------------------
            ->brandName('Gatezo')
            // Topbar and sidebar are plum in both modes, so the white wordmark is used for both.
            ->brandLogo(asset('brand/logo.png'))
            ->darkModeBrandLogo(asset('brand/logo.png'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('brand/mark.png'))
            ->font('Inter')
            ->colors([
                'primary' => Color::hex('#E8604C'), // coral
                'success' => Color::hex('#4A9B5E'),
                'warning' => Color::hex('#D9713C'),
                'danger' => Color::hex('#B23A48'),
                'gray' => Color::hex('#78716C'), // stone: warm neutral
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(PanelsRenderHook::TOPBAR_AFTER, fn () => view('filament.hooks.demo-banner'))
            // SPA mode: links inside the panel swap the page over Livewire instead of a full
            // reload, so there's no blank frame between screens. Print/CSV/board links open
            // real documents, so they're excluded and still open normally.
            ->spa()
            ->spaUrlExceptions(fn (): array => [
                url('/print/*'),
                url('/e/*'),
                url('/vendor/*'),
                url('/scan*'),
                url('/pass/*'),
                url('/stall/*'),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('17rem')
            ->maxContentWidth(Width::Full)
            ->navigationGroups([
                NavigationGroup::make('Live')->icon(Heroicon::OutlinedSignal),
                NavigationGroup::make('People & gates')->icon(Heroicon::OutlinedUsers),
                NavigationGroup::make('Stalls & feedback')->icon(Heroicon::OutlinedBuildingStorefront),
            ])

            // ---- Tenancy -----------------------------------------------------
            ->tenant(Event::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterEvent::class)
            ->tenantProfile(EditEventProfile::class)

            // ---- Discovery ---------------------------------------------------
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')

            // ---- Middleware --------------------------------------------------
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
