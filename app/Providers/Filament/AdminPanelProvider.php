<?php

namespace App\Providers\Filament;

use App\Filament\Support\InitialsAvatarProvider;
use App\Filament\Support\NavGroup;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /** Teal klinik; shade 600 = #0D6E63 (tombol utama), 700 = #0A5A51 (hover). */
    private const TEAL = [
        50 => '#effaf8', 100 => '#d6f1ec', 200 => '#ade2d9', 300 => '#7ccbbe', 400 => '#3fae9e', 500 => '#168a7b',
        600 => '#0d6e63', 700 => '#0a5a51', 800 => '#0b4842', 900 => '#0b3b36', 950 => '#04221f',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->brandName('Dental PMS')
            ->darkMode(false)
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
                EmailAuthentication::make(),
            ], isRequired: true)
            ->databaseNotifications()
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // Fondasi UI mockup v2: teal #0D6E63 hanya untuk aksi utama & posisi aktif, abu-abu zinc untuk sisanya.
            // Palet teal ditulis manual: Color::hex() hanya mengambil hue sehingga tombol jadi toska terang.
            ->colors([
                'primary' => self::TEAL,
                'gray' => Color::Zinc,
                'violet' => Color::Violet,
            ])
            ->font('IBM Plex Sans')
            ->monoFont('IBM Plex Mono')
            ->navigationGroups(NavGroup::ordered())
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup(NavGroup::SETTINGS)
                    ->navigationLabel('Peran & Hak Akses')
                    ->navigationSort(2),
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render('<x-pwa-head /><x-touch-targets /><x-panel-styles />'))
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
