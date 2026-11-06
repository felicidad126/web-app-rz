<?php

namespace App\Services\Clinic;

use App\Enums\OdontogramEntryType;
use App\Models\OdontogramEntry;
use App\Models\Patient;
use App\Support\Teeth;
use Illuminate\Support\Collection;

/**
 * Status odontogram terkini dihitung dari riwayat entri (tidak pernah ditimpa).
 *
 * Aturan:
 * - per gigi/permukaan, entri kondisi/dikerjakan terakhir yang berlaku;
 * - entri seluruh gigi (permukaan kosong) menggantikan entri permukaan yang lebih lama;
 * - rencana tetap tampil sampai ada entri "dikerjakan" yang lebih baru di gigi/permukaan yang sama.
 */
final class OdontogramChart
{
    /** Kode yang dihitung pada indeks DMF-T / def-t (draft, cocokkan dengan dokter klinik). */
    public const DECAYED = ['car', 'rrx'];

    public const MISSING = ['mis'];

    public const FILLED = ['amf', 'cof', 'gif', 'inl', 'onl', 'fmc', 'poc', 'mpc'];

    /** @var Collection<int, OdontogramEntry> */
    private Collection $entries;

    public function __construct(Patient $patient)
    {
        $this->entries = $patient->odontogramEntries()
            ->with('condition')
            ->orderBy('id')
            ->get();
    }

    /**
     * Ringkasan per gigi untuk tampilan & cetak.
     *
     * @return array<int|string, array{current: list<OdontogramEntry>, planned: list<OdontogramEntry>}>
     */
    public function teeth(): array
    {
        $result = [];

        foreach ($this->entries->groupBy('tooth_number') as $tooth => $entries) {
            $result[(string) $tooth] = [
                'current' => $this->currentFor($entries),
                'planned' => $this->plannedFor($entries),
            ];
        }

        return $result;
    }

    /** @return Collection<int, OdontogramEntry> semua entri satu gigi, terlama dulu */
    public function entriesFor(string $tooth): Collection
    {
        return $this->entries->filter(fn (OdontogramEntry $e): bool => $e->tooth_number === $tooth)->values();
    }

    /** @return array{D: int, M: int, F: int, total: int, d: int, e: int, f: int, total_primary: int} */
    public function indices(): array
    {
        $counts = ['D' => 0, 'M' => 0, 'F' => 0, 'd' => 0, 'e' => 0, 'f' => 0];

        foreach ($this->teeth() as $tooth => $state) {
            $codes = collect($state['current'])->pluck('condition_code')->all();
            $permanent = Teeth::isPermanent((string) $tooth);

            if (array_intersect($codes, self::DECAYED)) {
                $counts[$permanent ? 'D' : 'd']++;
            } elseif (array_intersect($codes, self::MISSING)) {
                $counts[$permanent ? 'M' : 'e']++;
            } elseif (array_intersect($codes, self::FILLED)) {
                $counts[$permanent ? 'F' : 'f']++;
            }
        }

        return [
            ...$counts,
            'total' => $counts['D'] + $counts['M'] + $counts['F'],
            'total_primary' => $counts['d'] + $counts['e'] + $counts['f'],
        ];
    }

    /**
     * @param  Collection<int, OdontogramEntry>  $entries
     * @return list<OdontogramEntry>
     */
    private function currentFor(Collection $entries): array
    {
        $applied = $entries->filter(fn (OdontogramEntry $e): bool => $e->entry_type !== OdontogramEntryType::Planned);
        $wholeTooth = $applied->filter(fn (OdontogramEntry $e): bool => $e->surface === null)->last();

        $surfaces = $applied
            ->filter(fn (OdontogramEntry $e): bool => $e->surface !== null && ($wholeTooth === null || $e->id > $wholeTooth->id))
            ->keyBy(fn (OdontogramEntry $e): string => $e->surface->value)
            ->values()
            ->all();

        $current = $wholeTooth && $wholeTooth->condition_code !== 'sou' ? [$wholeTooth] : [];

        return [...$current, ...$surfaces];
    }

    /**
     * @param  Collection<int, OdontogramEntry>  $entries
     * @return list<OdontogramEntry>
     */
    private function plannedFor(Collection $entries): array
    {
        return $entries
            ->filter(fn (OdontogramEntry $e): bool => $e->entry_type === OdontogramEntryType::Planned)
            ->reject(fn (OdontogramEntry $plan): bool => $entries->contains(
                fn (OdontogramEntry $e): bool => $e->entry_type === OdontogramEntryType::Done
                    && $e->id > $plan->id
                    && $e->surface === $plan->surface,
            ))
            ->values()
            ->all();
    }
}
