<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Filament\Support\InitialsAvatarProvider;
use App\Filament\Support\NavGroup;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Ungu (Konsep UI inventori, disepakati 9 Nov 2026): 600 = #6C5DD3 tombol utama (teks putih 5,07:1),
     * 700 = #5B4BC4 hover & teks menu aktif, 50 = #F1EFFF sorotan menu aktif.
     */
    private const VIOLET = [
        50 => '#f1efff', 100 => '#e4e0ff', 200 => '#ccc5fb', 300 => '#ada2f5', 400 => '#8f80ea', 500 => '#7b6cde',
        600 => '#6c5dd3', 700 => '#5b4bc4', 800 => '#4a3ca3', 900 => '#3d3384', 950 => '#251f52',
    ];

    /**
     * Abu-abu kebiruan (navy): 50 = #F6F6FB latar halaman, 500 = #6B6C80 teks sekunder (AA di putih & latar),
     * 950 = #11142D teks utama. Ditulis manual karena Color::hex() hanya mengambil rona.
     */
    private const NAVY_GRAY = [
        50 => '#f6f6fb', 100 => '#eeeef5', 200 => '#e3e3ec', 300 => '#cdcedb', 400 => '#8a8ca2', 500 => '#6b6c80',
        600 => '#55566a', 700 => '#3f4157', 800 => '#2a2d47', 900 => '#1b1e3a', 950 => '#11142d',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->brandName(fn (): string => (string) config('app.name'))
            // Logo klinik (public/icons/logo.svg) + nama; favicon & ikon PWA dari logo yang sama.
            ->brandLogo(fn (): View => view('filament.brand-logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn (): string => asset('favicon.png').'?v='.(is_file(public_path('favicon.png')) ? filemtime(public_path('favicon.png')) : 0))
            // Lebar penuh: tabel & dashboard memakai seluruh layar; teks panjang (Panduan) dibatasi di view-nya sendiri.
            ->maxContentWidth(Width::Full)
            ->darkMode(false)
            ->login()
            ->passwordReset()
            ->profile(EditProfile::class, isSimple: false)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
                EmailAuthentication::make(),
            ], isRequired: true)
            ->databaseNotifications()
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // Konsep UI inventori: ungu hanya untuk aksi utama & posisi aktif, abu-abu navy untuk sisanya.
            ->colors([
                'primary' => self::VIOLET,
                'gray' => self::NAVY_GRAY,
                'violet' => Color::Violet,
            ])
            // Font di-host sendiri (public/fonts): tanpa CDN pihak ketiga, ikut tersimpan oleh service worker.
            // Inter untuk teks/menu/tabel; Plus Jakarta Sans untuk judul & angka besar (klinik.css); Plex Mono untuk kode.
            ->font('Inter', url: asset('fonts/inter/inter.css'), provider: LocalFontProvider::class)
            ->monoFont('IBM Plex Mono', url: asset('fonts/plex/plex.css'), provider: LocalFontProvider::class)
            // Kelompok menu selalu terbuka (tanpa tombol buka/tutup), seperti mockup.
            ->navigationGroups(array_map(
                fn (string $label): NavigationGroup => NavigationGroup::make($label)->collapsible(false),
                NavGroup::ordered(),
            ))
            // Tanpa bilah atas: logo, cari, notifikasi, dan menu pengguna ada di sidebar (disepakati 10 Nov 2026).
            ->topbar(false)
            ->sidebarWidth('17rem')
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
                    ->gridColumns(['default' => 1, 'lg' => 2])
                    ->resourceCheckboxListColumns(['default' => 2, 'sm' => 4])
                    ->navigationGroup(NavGroup::SETTINGS)
                    ->navigationLabel('Peran & hak akses')
                    ->navigationSort(2),
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render('<x-pwa-head /><x-touch-targets /><x-panel-styles />'))
            // Kop klinik saat halaman dicetak (disembunyikan di layar).
            ->renderHook(PanelsRenderHook::CONTENT_START, fn (): View => view('filament.print-header'))
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
