<?php

namespace App\Filament\Pages;

use App\Filament\Support\NavGroup;
use App\Models\User;
use App\Support\GuideContent;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Panduan per peran (pusat bantuan): judul + kotak cari, kartu per peran, pertanyaan umum; isi satu peran di
 * halaman sendiri (?peran=…) dengan daftar topik di kiri. Isi dari resources/guides/*.md (lihat GuideContent)
 * agar mudah diubah tanpa menyentuh kode. Pengguna melihat bagian umum + perannya; owner/admin melihat semua.
 */
class Guide extends Page
{
    /** Urutan kartu: peran → judul. */
    public const ROLES = [
        'umum' => 'Umum',
        'resepsionis' => 'Resepsionis',
        'dokter' => 'Dokter',
        'perawat' => 'Perawat',
        'kasir' => 'Kasir',
        'admin' => 'Admin klinik',
        'owner' => 'Owner',
    ];

    /** Keterangan singkat di kartu peran. */
    public const DESCRIPTIONS = [
        'umum' => 'Login & MFA, pasang di HP, pilih cabang, bila ada masalah.',
        'resepsionis' => 'Pendaftaran pasien, janji temu, antrean, recall.',
        'dokter' => 'Pemeriksaan, odontogram, rekam medis & tanda tangan.',
        'perawat' => 'Bantu pemeriksaan, stok, minta beli, terima barang, opname.',
        'kasir' => 'Tagihan, pembayaran, deposit, paket & cicilan, tutup kasir.',
        'admin' => 'Akun & akses, master data, persetujuan, pembelian.',
        'owner' => 'Briefing pagi, laporan, target, jasa & gaji, kas pembelian.',
    ];

    /** Peran yang sedang dibuka (?peran=kasir); kosong = halaman utama Panduan. */
    #[Url(as: 'peran')]
    public ?string $peran = null;

    protected string $view = 'filament.pages.guide';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::HELP;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Panduan';

    protected static ?string $title = 'Panduan';

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

    /** Judul besar "Butuh bantuan?" ada di isi halaman utama; di halaman peran, judulnya nama peran. */
    public function getHeading(): string
    {
        return $this->activeRole() !== null ? self::ROLES[$this->activeRole()] : '';
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return $this->activeRole() !== null ? [static::getUrl() => 'Panduan'] : [];
    }

    public function activeRole(): ?string
    {
        $sections = self::sectionsFor($this->user());

        return $this->peran !== null && isset($sections[$this->peran]) ? $this->peran : null;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = $this->user();
        $sections = self::sectionsFor($user);
        $role = $this->activeRole();

        if ($role !== null) {
            return [
                'role' => $role,
                'topics' => GuideContent::topics($role, self::ROLES[$role]),
                'indexUrl' => static::getUrl(),
            ];
        }

        $cards = [];
        $search = [];

        foreach ($sections as $key => $title) {
            $url = static::getUrl(['peran' => $key]);
            $cards[$key] = [
                'title' => $title,
                'description' => self::DESCRIPTIONS[$key] ?? '',
                'url' => $url,
                'mine' => $key !== 'umum' && $user?->hasRole($key),
            ];

            foreach (GuideContent::topics($key, $title) as $topic) {
                $search[] = self::searchEntry($title, $topic['title'], $topic['text'], $url.'#'.$topic['slug']);
            }
        }

        $faq = GuideContent::topics('faq');

        foreach ($faq as $i => $item) {
            $search[] = self::searchEntry('Pertanyaan umum', $item['title'], $item['text'], '#faq-'.$i);
        }

        return ['role' => null, 'cards' => $cards, 'faq' => $faq, 'search' => $search];
    }

    /** @return array{section: string, title: string, snippet: string, url: string, haystack: string} */
    private static function searchEntry(string $section, string $title, string $text, string $url): array
    {
        return [
            'section' => $section,
            'title' => $title,
            'snippet' => Str::limit($text, 140),
            'url' => $url,
            'haystack' => Str::lower(Str::ascii($section.' '.$title.' '.$text)),
        ];
    }

    private function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
