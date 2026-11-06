<?php

namespace App\Reports;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\StockMovementStatus;
use App\Enums\StockMovementType;
use App\Models\User;
use App\Services\Reports\TreatmentMargins;
use Illuminate\Support\Facades\DB;

/** Laba kotor: per tindakan (setelah diskon − bahan − lab − jasa dokter) + obat & barang (penjualan − HPP). Owner saja. */
class ProfitReport extends Report
{
    public static function key(): string
    {
        return 'laba-kotor';
    }

    public static function label(): string
    {
        return 'Laba kotor';
    }

    public static function description(): string
    {
        return 'Per tindakan: nilai setelah diskon − HPP bahan − biaya lab − jasa dokter (menurut tanggal kunjungan). Obat & barang: penjualan − harga pokok dari mutasi stok keluar di periode yang sama.';
    }

    public static function canView(User $user): bool
    {
        return $user->can('View:ProfitReports');
    }

    public function columns(): array
    {
        return [
            ['label' => 'Tindakan / kelompok', 'type' => 'text'],
            ['label' => 'Jumlah', 'type' => 'number'],
            ['label' => 'Pendapatan', 'type' => 'money'],
            ['label' => 'Bahan / HPP', 'type' => 'money'],
            ['label' => 'Biaya lab', 'type' => 'money'],
            ['label' => 'Jasa dokter', 'type' => 'money'],
            ['label' => 'Laba kotor', 'type' => 'money'],
            ['label' => 'Margin', 'type' => 'percent'],
        ];
    }

    public function rows(): array
    {
        $rows = TreatmentMargins::between($this->filters->from, $this->filters->to, $this->filters->branchIds)
            ->map(fn ($r): array => [$r->name, $r->qty, $r->revenue, $r->material, $r->lab, $r->commission, $r->margin, $r->revenue > 0 ? $r->margin / $r->revenue : null])
            ->all();

        $sales = (int) DB::table('invoice_items')->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoice_items.item_type', InvoiceItemType::Item->value)
            ->whereIn('invoices.status', [InvoiceStatus::Issued->value, InvoiceStatus::Partial->value, InvoiceStatus::Paid->value])
            ->whereIn('invoices.branch_id', $this->filters->branchIds)
            ->whereDate('invoices.issued_at', '>=', $this->filters->from->toDateString())->whereDate('invoices.issued_at', '<=', $this->filters->to->toDateString())
            ->sum('invoice_items.net_amount');
        $cost = -(int) DB::table('stock_movement_lines')->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->where('stock_movements.status', StockMovementStatus::Posted->value)
            ->whereIn('stock_movements.type', [StockMovementType::Sale->value, StockMovementType::Dispensing->value])
            ->whereIn('stock_movements.branch_id', $this->filters->branchIds)
            ->whereDate('stock_movements.movement_date', '>=', $this->filters->from->toDateString())->whereDate('stock_movements.movement_date', '<=', $this->filters->to->toDateString())
            ->sum('stock_movement_lines.line_cost');

        if ($sales > 0 || $cost > 0) {
            $rows[] = ['Obat & barang (resep, retail)', null, $sales, $cost, 0, 0, $sales - $cost, $sales > 0 ? ($sales - $cost) / $sales : null];
        }

        return $rows;
    }

    public function totals(): ?array
    {
        $rows = $this->rows();
        $total = $this->sumColumns($rows, [2, 3, 4, 5, 6]);
        $total[7] = $total[2] > 0 ? $total[6] / $total[2] : null;

        return $total;
    }
}
