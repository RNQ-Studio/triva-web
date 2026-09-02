<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\StarterOverview;
use App\Http\Middleware\CheckMaintenance;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->darkMode(false)
            ->login()
            ->brandName('Laravel Starter')
            ->brandLogo(asset('images/logo-light.svg'))
            ->darkModeBrandLogo(asset('images/logo-dark.svg'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::Blue,
            ])
            ->databaseNotifications()
            ->discoverResources(app_path('Filament/Resources'), 'App\Filament\Resources')
            ->discoverPages(app_path('Filament/Pages'), 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(app_path('Filament/Widgets'), 'App\Filament\Widgets')
            ->widgets([
                StarterOverview::class,
                AccountWidget::class,
            ])
            ->middleware([
                // Panel ini punya stack sendiri, tidak memakai grup `web`,
                // jadi sakelar maintenance harus didaftarkan terpisah agar
                // `TRIVA_MAINTENANCE_ALLOW_ADMIN=false` benar-benar mengunci
                // back-office. Default-nya panel tetap dibiarkan hidup.
                CheckMaintenance::class,
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
