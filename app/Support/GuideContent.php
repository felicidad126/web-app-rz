<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Membaca panduan pengguna (resources/guides/*.md) menjadi daftar topik untuk halaman Panduan.
 *
 * Sebuah topik dimulai oleh baris "## Judul" atau oleh paragraf "**Judul** — isi …". Baris "## <judul peran>"
 * di awal file peran dilewati karena judul peran sudah tampil di halaman.
 */
final class GuideContent
{
    /**
     * @return list<array{title: string, slug: string, html: string, text: string}>
     */
    public static function topics(string $name, ?string $skipHeading = null): array
    {
        $path = resource_path("guides/{$name}.md");

        if (! is_file($path)) {
            return [];
        }

        $topics = [];
        $current = null;

        foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
            if (preg_match('/^##\s+(.+)$/', $line, $m)) {
                if ($skipHeading !== null && $current === null && $topics === [] && trim($m[1]) === $skipHeading) {
                    continue;
                }

                $topics[] = $current = ['title' => trim($m[1]), 'body' => []];

                continue;
            }

            if (preg_match('/^\*\*(.+?)\*\*\s+—\s+(.*)$/u', $line, $m)) {
                $topics[] = $current = ['title' => trim($m[1]), 'body' => [Str::ucfirst($m[2])]];

                continue;
            }

            if ($current !== null) {
                $topics[array_key_last($topics)]['body'][] = $line;
            }
        }

        $used = [];

        return array_map(function (array $topic) use (&$used): array {
            $html = Str::markdown(trim(implode("\n", $topic['body'])), ['html_input' => 'escape', 'allow_unsafe_links' => false]);
            $base = Str::slug($topic['title']) ?: 'topik';
            $slug = $base;

            for ($n = 2; isset($used[$slug]); $n++) {
                $slug = $base.'-'.$n;
            }

            $used[$slug] = true;

            return [
                'title' => $topic['title'],
                'slug' => $slug,
                'html' => $html,
                'text' => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html)))),
            ];
        }, $topics);
    }
}
