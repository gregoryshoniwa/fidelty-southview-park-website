<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Support\AdminAudit;
use App\Filament\Support\Uploads;
use App\Filament\Widgets;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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

class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        Uploads::registerDisk();
    }

    public function boot(): void
    {
        AdminAudit::register();
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->brandName('Southview Park Committee')
                    ->recoverable(),
            ], isRequired: true)
            ->brandName('Southview Park Committee')
            ->brandLogo('/images/logo-128.webp')
            ->brandLogoHeight('3.5rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font('Manrope Variable', provider: \Filament\FontProviders\LocalFontProvider::class)
            ->favicon('/favicon.ico')
            ->colors([
                'primary' => [
                    50 => 'oklch(0.97 0.015 155)', 100 => 'oklch(0.93 0.03 155)', 200 => 'oklch(0.86 0.06 155)',
                    300 => 'oklch(0.74 0.09 155)', 400 => 'oklch(0.58 0.1 155)', 500 => 'oklch(0.47 0.09 155)',
                    600 => 'oklch(0.39 0.08 155)', 700 => 'oklch(0.34 0.07 155)', 800 => 'oklch(0.29 0.06 155)',
                    900 => 'oklch(0.25 0.05 155)', 950 => 'oklch(0.18 0.04 155)',
                ],
                'gray' => Color::Stone,
                'warning' => Color::Amber,
            ])
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->navigationGroups([
                'Inbox',
                'Content',
                'Services and partners',
                'Community',
                'Finance',
                'System',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                Widgets\CommitteeStats::class,
                Widgets\LatestInboxThreads::class,
                Widgets\FinanceStats::class,
                Widgets\LedgerOverview::class,
            ])
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
