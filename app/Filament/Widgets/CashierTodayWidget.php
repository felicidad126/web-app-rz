<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VisitStatus;
use App\Models\Invoice;
use App\Models\Visit;
use App\Support\BranchContext;
use App\Support\Money;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/** Dashboard kasir: kas hari ini per metode (untuk tutup kasir), pasien hari ini yang belum lunas, tagihan sebagian. */
class CashierTodayWidget extends StatsOverviewWidget
{
    use HasWidgetShield {
        canView as shieldCanView;
    }

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return static::shieldCanView() && BranchContext::id() !== null;
    }

    protected function getHeading(): ?string
    {
        return 'Kasir hari ini · '.BranchContext::available()->firstWhere('id', BranchContext::id())?->name;
    }

    protected function getStats(): array
    {
        $branchId = (int) BranchContext::id();
        $byMethod = DB::table('payments')->join('payment_transactions', 'payment_transactions.id', '=', 'payments.payment_transaction_id')
            ->where('payment_transactions.branch_id', $branchId)->whereDate('payment_transactions.paid_at', today())
            ->where('payments.status', '!=', PaymentStatus::Void->value)
            ->groupBy('payments.method')->selectRaw('payments.method, sum(payments.amount) as total')->pluck('total', 'method');

        $unpaidToday = Visit::query()->where('branch_id', $branchId)->whereDate('visit_date', today())
            ->whereIn('status', [VisitStatus::InTreatment, VisitStatus::Done, VisitStatus::Billed])
            ->where(fn ($q) => $q->whereDoesntHave('invoices', fn ($i) => $i->where('status', '!=', InvoiceStatus::Void))
                ->orWhereHas('invoices', fn ($i) => $i->whereIn('status', [InvoiceStatus::Draft, InvoiceStatus::Issued, InvoiceStatus::Partial])))
            ->count();
        $partial = Invoice::query()->where('branch_id', $branchId)->where('status', InvoiceStatus::Partial)->count();

        $methods = collect(PaymentMethod::cases())->filter(fn (PaymentMethod $m): bool => (int) ($byMethod[$m->value] ?? 0) > 0)
            ->map(fn (PaymentMethod $m): string => $m->getLabel().' '.Money::format((int) $byMethod[$m->value]))->implode(' · ');

        return [
            Stat::make('Diterima hari ini', Money::format((int) $byMethod->sum()))
                ->description($methods ?: 'Belum ada pembayaran'),
            Stat::make('Pasien hari ini belum lunas', (string) $unpaidToday)
                ->description('Termasuk yang belum dibuat tagihannya')
                ->color($unpaidToday > 0 ? 'warning' : 'gray'),
            Stat::make('Tagihan dibayar sebagian', (string) $partial)->description('Semua tanggal, cabang ini'),
        ];
    }
}
