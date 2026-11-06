<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Panduan singkat per peran untuk pelatihan & rollout (Fase 7). Isi dari resources/guides/*.md agar mudah diubah
 * tanpa menyentuh kode. Pengguna melihat bagian umum + perannya; owner/admin melihat semua.
 */
class Guide extends Page
{
    /** Urutan tab: peran → judul. */
    public const ROLES = [
        'umum' => 'Umum',
        'resepsionis' => 'Resepsionis',
        'dokter' => 'Dokter',
        'perawat' => 'Perawat',
        'kasir' => 'Kasir',
        'admin' => 'Admin klinik',
        'owner' => 'Owner',
    ];

    protected string $view = 'filament.pages.guide';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?int $navigationSort = 1000;

    protected static ?string $navigationLabel = 'Panduan';

    protected static ?string $title = 'Panduan singkat';

    protected static ?string $slug = 'panduan';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /** @return array<string, string> peran → judul yang boleh dilihat pengguna ini */
    public static function sectionsFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $all = $user->hasAnyRole(['owner', 'admin', config('filament-shield.super_admin.name')]);

        return array_filter(self::ROLES, fn (string $title, string $role): bool => $role === 'umum' || $all || $user->hasRole($role), ARRAY_FILTER_USE_BOTH);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $sections = [];

        foreach (self::sectionsFor(auth()->user()) as $role => $title) {
            $path = resource_path("guides/{$role}.md");
            $sections[$role] = [
                'title' => $title,
                'html' => is_file($path) ? Str::markdown((string) file_get_contents($path), ['html_input' => 'escape', 'allow_unsafe_links' => false]) : '',
            ];
        }

        return ['sections' => $sections];
    }
}
