<?php

namespace App\Services\Clinic;

use App\Enums\OdontogramCategory;
use App\Enums\OdontogramEntryType;
use App\Enums\OdontogramScope;
use App\Models\OdontogramCondition;
use App\Models\OdontogramEntry;
use App\Models\Visit;
use App\Support\Teeth;

/**
 * Data gambar odontogram (mockup v2 "Odontogram"): tiap gigi kotak 40×40 dengan lima permukaan
 * (atas, kanan, bawah, kiri, tengah). Dipakai komponen interaktif (Alpine) dan PDF (SVG statis).
 *
 * Pemetaan permukaan (FDI, kanan pasien di kiri layar):
 * - tengah = O (oklusal/insisal);
 * - rahang atas: atas = V (bukal/labial), bawah = L (palatal); rahang bawah: atas = L (lingual), bawah = V;
 * - kuadran 1, 4, 5, 8: kanan = M, kiri = D; kuadran 2, 3, 6, 7: kiri = M, kanan = D.
 */
final class OdontogramVisual
{
    public const DONE_HERE = '#2e90fa';

    public const PLAN_FILL = '#fda29b';

    public const PLAN_STROKE = '#d92d20';

    private const EMPTY = '#ffffff';

    private const EMPTY_PRIMARY = '#fafafa';

    /** @var array<string, OdontogramCondition> */
    private array $conditions;

    public function __construct(private readonly OdontogramChart $chart, private readonly ?Visit $visit = null)
    {
        $this->conditions = OdontogramCondition::query()->orderBy('sort')->get()->keyBy('code')->all();
    }

    /**
     * @return array{teeth: array<int|string, array<string, mixed>>, rows: array<int, array<int, string>>, conditions: list<array<string, mixed>>, legend: list<array<string, string>>, indices: array<string, int>}
     */
    public function data(): array
    {
        $state = $this->chart->teeth();
        $teeth = [];
        $used = [];

        foreach (Teeth::ROWS as $row) {
            foreach ($row as $tooth) {
                $teeth[$tooth] = $this->tooth($tooth, $state[(int) $tooth] ?? ['current' => [], 'planned' => []], $used);
            }
        }

        $legend = collect($used)->unique()->map(fn (string $code): array => [
            'label' => $this->conditions[$code]->name ?? $code, 'code' => $code, 'color' => $this->color($code),
        ])->values()->all();

        if (collect($teeth)->contains(fn (array $t): bool => $t['plan'])) {
            $legend[] = ['label' => 'Rencana', 'code' => '', 'color' => self::PLAN_STROKE];
        }

        if ($this->visit !== null && collect($teeth)->contains(fn (array $t): bool => $t['here'])) {
            $legend[] = ['label' => 'Dikerjakan di kunjungan ini', 'code' => '', 'color' => self::DONE_HERE];
        }

        return [
            'teeth' => $teeth,
            'rows' => Teeth::ROWS,
            'conditions' => collect($this->conditions)->filter(fn (OdontogramCondition $c): bool => $c->is_active)->map(fn (OdontogramCondition $c): array => [
                'code' => $c->code, 'name' => $c->name, 'color' => $c->color ?: '#52525b',
                'scope' => $c->applies_to->value, 'category' => $c->category->getLabel(),
            ])->values()->all(),
            'legend' => $legend,
            'indices' => $this->chart->indices(),
        ];
    }

    /** @return array{t: string, r: string, b: string, l: string, c: string} titik poligon dalam kotak 40×40 */
    public static function polygons(string $tooth): array
    {
        $anterior = (int) $tooth[1] <= 3;
        [$x1, $y1] = $anterior ? [10, 17] : [12, 12];
        [$x2, $y2] = [40 - $x1, 40 - $y1];

        return [
            't' => "0,0 40,0 {$x2},{$y1} {$x1},{$y1}",
            'r' => "40,0 40,40 {$x2},{$y2} {$x2},{$y1}",
            'b' => "0,40 40,40 {$x2},{$y2} {$x1},{$y2}",
            'l' => "0,0 {$x1},{$y1} {$x1},{$y2} 0,40",
            'c' => "{$x1},{$y1} {$x2},{$y1} {$x2},{$y2} {$x1},{$y2}",
        ];
    }

    /** @return array{t: string, r: string, b: string, l: string, c: string} kode permukaan (M/O/D/V/L) per bagian gambar */
    public static function surfaceMap(string $tooth): array
    {
        $quadrant = (int) $tooth[0];
        $upper = in_array($quadrant, [1, 2, 5, 6], true);
        $mesialRight = in_array($quadrant, [1, 4, 5, 8], true);

        return [
            't' => $upper ? 'V' : 'L',
            'b' => $upper ? 'L' : 'V',
            'l' => $mesialRight ? 'D' : 'M',
            'r' => $mesialRight ? 'M' : 'D',
            'c' => 'O',
        ];
    }

    /** Satu SVG utuh (untuk PDF): empat baris FDI, nomor gigi, simbol, dan kode. */
    public function svg(): string
    {
        $data = $this->data();
        $size = 30;
        $gap = 6;
        $mid = 14;
        $rowHeight = 62;
        $width = 16 * $size + 15 * $gap + $mid;
        $out = [];

        foreach ($data['rows'] as $index => $row) {
            $offset = in_array($index, [1, 2], true) ? 3 * ($size + $gap) : 0;
            $y = $index * $rowHeight + ($index >= 2 ? 10 : 0);
            $upper = $index <= 1;

            foreach ($row as $i => $tooth) {
                $t = $data['teeth'][$tooth];
                $x = $offset + $i * ($size + $gap) + ($i >= count($row) / 2 ? $mid : 0);
                $boxY = $upper ? $y + 14 : $y;
                $scale = $size / 40;
                $shapes = '';

                foreach (['t', 'r', 'b', 'l', 'c'] as $k) {
                    $shapes .= '<polygon points="'.$t['poly'][$k].'" fill="'.$t['fill'][$k].'" stroke="#71717a" stroke-width="0.8"/>';
                }

                if ($t['mark'] === 'mis') {
                    $shapes .= '<line x1="2" y1="2" x2="38" y2="38" stroke="#18181b" stroke-width="2.5"/><line x1="38" y1="2" x2="2" y2="38" stroke="#18181b" stroke-width="2.5"/>';
                } elseif ($t['mark'] === 'rrx') {
                    $shapes .= '<polyline points="6,6 20,34 34,6" fill="none" stroke="#18181b" stroke-width="2.5"/>';
                }

                if ($t['ring'] !== null) {
                    $shapes .= '<rect x="1.5" y="1.5" width="37" height="37" fill="none" stroke="'.$t['ring'].'" stroke-width="3"/>';
                }

                if ($t['plan']) {
                    $shapes .= '<rect x="0.5" y="0.5" width="39" height="39" fill="none" stroke="'.self::PLAN_STROKE.'" stroke-width="1.6" stroke-dasharray="3 2"/>';
                }

                $numberY = $upper ? $y + 10 : $y + $size + 11;
                $labelY = $upper ? $y + $size + 25 : $y + $size + 22;
                $out[] = '<g transform="translate('.$x.','.$boxY.') scale('.$scale.')">'.$shapes.'</g>'
                    .'<text x="'.($x + $size / 2).'" y="'.$numberY.'" font-size="8" text-anchor="middle" fill="'.($t['primary'] ? '#a1a1aa' : '#3f3f46').'" font-family="DejaVu Sans">'.$tooth.'</text>'
                    .($t['label'] !== '' ? '<text x="'.($x + $size / 2).'" y="'.$labelY.'" font-size="7" text-anchor="middle" fill="'.$t['labelColor'].'" font-family="DejaVu Sans">'.e($t['label']).'</text>' : '');
            }
        }

        $height = 4 * $rowHeight + 20;

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">'.implode('', $out).'</svg>';
    }

    /**
     * @param  array{current: list<OdontogramEntry>, planned: list<OdontogramEntry>}  $state
     * @param  list<string>  $used
     * @return array<string, mixed>
     */
    private function tooth(string $tooth, array $state, array &$used): array
    {
        $primary = ! Teeth::isPermanent($tooth);
        $map = self::surfaceMap($tooth);
        $byLetter = array_flip($map);
        $fill = array_fill_keys(['t', 'r', 'b', 'l', 'c'], $primary ? self::EMPTY_PRIMARY : self::EMPTY);
        $mark = null;
        $ring = null;
        $here = false;
        $labels = [];
        $labelColor = '#3f3f46';

        foreach ($state['current'] as $entry) {
            $isHere = $this->isHere($entry);
            $here = $here || $isHere;
            $color = $isHere ? self::DONE_HERE : $this->color($entry->condition_code);
            $used[] = $entry->condition_code;
            $labels[] = $entry->condition_code;

            if ($entry->surface !== null) {
                $fill[$byLetter[$entry->surface->value]] = $entry->condition_code === 'sou' ? $fill['c'] : $color;

                continue;
            }

            match (true) {
                $entry->condition_code === 'mis' => $mark = 'mis',
                $entry->condition_code === 'rrx' => $mark = 'rrx',
                ($this->conditions[$entry->condition_code]->category ?? null) === OdontogramCategory::Prosthesis => $ring = $color,
                default => null,
            };
        }

        foreach ($state['planned'] as $entry) {
            if ($entry->surface !== null && $fill[$byLetter[$entry->surface->value]] === ($primary ? self::EMPTY_PRIMARY : self::EMPTY)) {
                $fill[$byLetter[$entry->surface->value]] = self::PLAN_FILL;
            }
        }

        if ($labels === [] && $state['planned'] !== []) {
            $labels = collect($state['planned'])->pluck('condition_code')->all();
            $labelColor = self::PLAN_STROKE;
        } elseif ($here) {
            $labelColor = self::DONE_HERE;
        }

        return [
            'n' => $tooth,
            'name' => Teeth::name($tooth),
            'upper' => in_array((int) $tooth[0], [1, 2, 5, 6], true),
            'primary' => $primary,
            'poly' => self::polygons($tooth),
            'surface' => $map,
            'fill' => $fill,
            'mark' => $mark,
            'ring' => $ring,
            'plan' => $state['planned'] !== [],
            'here' => $here,
            'label' => implode(' ', array_slice(array_values(array_unique($labels)), 0, 2)),
            'labelColor' => $labelColor,
            'info' => $this->info($tooth, $state),
        ];
    }

    /**
     * Ringkasan untuk panel gigi terpilih.
     *
     * @param  array{current: list<OdontogramEntry>, planned: list<OdontogramEntry>}  $state
     * @return list<array{text: string, tone: string}>
     */
    private function info(string $tooth, array $state): array
    {
        $line = fn (OdontogramEntry $e): string => ($this->conditions[$e->condition_code]->name ?? $e->condition_code).' ('.$e->condition_code.')'
            .($e->surface ? ' '.$e->surface->value : '').' · '.$e->created_at?->format('d/m/Y');

        $lines = [];

        foreach ($state['current'] as $entry) {
            $lines[] = ['text' => ($this->isHere($entry) ? 'Kunjungan ini: ' : '').$line($entry), 'tone' => $this->isHere($entry) ? 'here' : ''];
        }

        foreach ($state['planned'] as $entry) {
            $lines[] = ['text' => 'Rencana: '.$line($entry), 'tone' => 'plan'];
        }

        if ($lines === []) {
            $older = $this->chart->entriesFor($tooth)->isNotEmpty();
            $lines[] = ['text' => $older ? 'Sehat (sou)' : (Teeth::isPermanent($tooth) ? 'Belum ada catatan' : 'Belum ada catatan (gigi sulung)'), 'tone' => 'muted'];
        }

        return $lines;
    }

    private function isHere(OdontogramEntry $entry): bool
    {
        return $this->visit !== null && $entry->visit_id === $this->visit->id && $entry->entry_type === OdontogramEntryType::Done;
    }

    private function color(string $code): string
    {
        return ($this->conditions[$code]->color ?? null) ?: '#52525b';
    }

    public static function scopeOf(string $code): ?OdontogramScope
    {
        return OdontogramCondition::query()->where('code', $code)->value('applies_to');
    }
}
