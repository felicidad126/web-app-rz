<?php

namespace App\Reports;

use App\Models\User;
use App\Support\CommissionScope;

/**
 * Laporan tabel yang bisa dilihat di halaman Laporan dan diekspor ke Excel/PDF dengan isi yang sama.
 *
 * Kolom: ['label' => string, 'type' => 'text'|'money'|'number'|'percent']. Baris: nilai mentah (uang = int rupiah,
 * persen = rasio 0..1 atau null), diformat saat ditampilkan.
 */
abstract class Report
{
    public function __construct(protected readonly ReportFilters $filters) {}

    public function filters(): ReportFilters
    {
        return $this->filters;
    }

    abstract public static function key(): string;

    abstract public static function label(): string;

    abstract public static function description(): string;

    abstract public static function canView(User $user): bool;

    /** @return list<array{label: string, type: string}> */
    abstract public function columns(): array;

    /** @return list<list<scalar|null>> */
    abstract public function rows(): array;

    /** @return list<scalar|null>|null */
    public function totals(): ?array
    {
        return null;
    }

    /** @return array<string, string> pilihan pengelompokan (kosong bila tidak ada) */
    public static function groupOptions(): array
    {
        return [];
    }

    /** Laporan ini memakai filter tanggal (piutang & kedaluwarsa memakai kondisi saat ini). */
    public static function usesPeriod(): bool
    {
        return true;
    }

    /** Pengguna tanpa izin laporan keuangan hanya melihat datanya sendiri (dokter). */
    public static function ownOnly(User $user): bool
    {
        return ! $user->can('View:FinancialReports');
    }

    public static function supportsPractitioner(): bool
    {
        return false;
    }

    protected static function isDoctor(User $user): bool
    {
        return CommissionScope::ownPractitionerId($user) !== null;
    }

    /**
     * Jumlahkan kolom angka dari baris.
     *
     * @param  list<list<scalar|null>>  $rows
     * @param  list<int>  $indexes
     * @return list<scalar|null>
     */
    protected function sumColumns(array $rows, array $indexes, string $label = 'Total'): array
    {
        $total = array_fill(0, count($this->columns()), null);
        $total[0] = $label;

        foreach ($indexes as $index) {
            $total[$index] = array_sum(array_map(fn (array $row) => (float) ($row[$index] ?? 0), $rows));

            if (in_array($this->columns()[$index]['type'], ['money', 'number'], true) && floor((float) $total[$index]) == $total[$index]) {
                $total[$index] = (int) $total[$index];
            }
        }

        return $total;
    }
}
