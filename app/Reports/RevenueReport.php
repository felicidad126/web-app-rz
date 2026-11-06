<?php

namespace App\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VisitStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Omzet per cabang / dokter / tindakan / penjamin (dasar produksi), atau per metode bayar (dasar kas). */
class RevenueReport extends Report
{
    public static function key(): string
    {
        return 'omzet';
    }

    public static function label(): string
    {
        return 'Omzet';
    }

    public static function description(): string
    {
        return 'Nilai tagihan dari kunjungan selesai menurut tanggal kunjungan (setelah diskon, termasuk nilai paket terpakai). "Metode bayar" memakai pembayaran yang diterima menurut tanggal bayar.';
    }

    public static function canView(User $user): bool
    {
        return $user->can('View:FinancialReports') || ($user->can('View:Reports') && self::isDoctor($user));
    }

    public static function supportsPractitioner(): bool
    {
        return true;
    }

    public static function groupOptions(): array
    {
        return ['branch' => 'Per cabang', 'practitioner' => 'Per dokter', 'treatment' => 'Per tindakan / barang', 'payer' => 'Per penjamin', 'method' => 'Per metode bayar'];
    }

    public function columns(): array
    {
        if ($this->filters->group === 'method') {
            return [['label' => 'Metode bayar', 'type' => 'text'], ['label' => 'Transaksi', 'type' => 'number'], ['label' => 'Diterima', 'type' => 'money']];
        }

        return [
            ['label' => self::groupOptions()[$this->group()] ?? 'Kelompok', 'type' => 'text'],
            ['label' => 'Item', 'type' => 'number'],
            ['label' => 'Bruto', 'type' => 'money'],
            ['label' => 'Diskon', 'type' => 'money'],
            ['label' => 'Setelah diskon', 'type' => 'money'],
            ['label' => 'Porsi', 'type' => 'percent'],
        ];
    }

    public function rows(): array
    {
        if ($this->filters->group === 'method') {
            return DB::table('payments')->join('payment_transactions', 'payment_transactions.id', '=', 'payments.payment_transaction_id')
                ->whereIn('payment_transactions.branch_id', $this->filters->branchIds)
                ->whereDate('payment_transactions.paid_at', '>=', $this->filters->from->toDateString())
                ->whereDate('payment_transactions.paid_at', '<=', $this->filters->to->toDateString())
                ->where('payments.status', '!=', PaymentStatus::Void->value)
                ->groupBy('payments.method')->selectRaw('payments.method, count(*) as n, sum(payments.amount) as total')
                ->orderByDesc('total')->get()
                ->map(fn ($r): array => [PaymentMethod::tryFrom($r->method)?->getLabel() ?? $r->method, (int) $r->n, (int) $r->total])->all();
        }

        [$key, $label] = match ($this->group()) {
            'practitioner' => ['invoice_items.practitioner_id', "coalesce(pu.name, 'Tanpa dokter (obat/barang)')"],
            'treatment' => ['invoice_items.description', 'invoice_items.description'],
            'payer' => ['invoices.payer_id', "coalesce(payers.name, 'Umum')"],
            default => ['visits.branch_id', 'branches.name'],
        };

        $rows = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('visits', 'visits.id', '=', 'invoices.visit_id')
            ->join('branches', 'branches.id', '=', 'visits.branch_id')
            ->leftJoin('payers', 'payers.id', '=', 'invoices.payer_id')
            ->leftJoin('practitioners as p', 'p.id', '=', 'invoice_items.practitioner_id')
            ->leftJoin('users as pu', 'pu.id', '=', 'p.user_id')
            ->where('invoices.status', '!=', InvoiceStatus::Void->value)
            ->whereIn('visits.status', [VisitStatus::Done->value, VisitStatus::Billed->value])
            ->whereIn('visits.branch_id', $this->filters->branchIds)
            ->whereDate('visits.visit_date', '>=', $this->filters->from->toDateString())
            ->whereDate('visits.visit_date', '<=', $this->filters->to->toDateString())
            ->when($this->filters->practitionerId !== null, fn ($q) => $q->where('invoice_items.practitioner_id', $this->filters->practitionerId))
            ->groupBy(DB::raw($key), DB::raw($label))
            ->selectRaw("{$label} as name, count(*) as n,
                sum(round(invoice_items.qty * invoice_items.unit_price) + invoice_items.package_value) as gross,
                sum(invoice_items.item_discount + invoice_items.allocated_invoice_discount) as discount,
                sum(invoice_items.net_amount + invoice_items.package_value) as net")
            ->orderByDesc('net')->get();

        $total = max(1, (int) $rows->sum('net'));

        return $rows->map(fn ($r): array => [(string) $r->name, (int) $r->n, (int) $r->gross, (int) $r->discount, (int) $r->net, (int) $r->net / $total])->all();
    }

    public function totals(): ?array
    {
        $rows = $this->rows();

        return $this->filters->group === 'method' ? $this->sumColumns($rows, [1, 2]) : $this->sumColumns($rows, [1, 2, 3, 4]);
    }

    private function group(): string
    {
        return $this->filters->group && isset(self::groupOptions()[$this->filters->group]) ? $this->filters->group : 'branch';
    }
}
