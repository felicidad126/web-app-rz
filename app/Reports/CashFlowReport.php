<?php

namespace App\Reports;

use App\Enums\DepositTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Arus kas harian per metode bayar: pembayaran + top-up deposit − refund (Kpi::CashIn). */
class CashFlowReport extends Report
{
    public static function key(): string
    {
        return 'arus-kas';
    }

    public static function label(): string
    {
        return 'Arus kas';
    }

    public static function description(): string
    {
        return 'Uang masuk per hari dan metode: pembayaran tagihan (selain dari saldo deposit), top-up deposit, dikurangi refund yang diserahkan.';
    }

    public static function canView(User $user): bool
    {
        return $user->can('View:FinancialReports') || $user->can('View:CashReport');
    }

    /** @return list<PaymentMethod> */
    private function methods(): array
    {
        return PaymentMethod::moneyIn();
    }

    public function columns(): array
    {
        return [
            ['label' => 'Tanggal', 'type' => 'text'],
            ...array_map(fn (PaymentMethod $m): array => ['label' => $m->getLabel(), 'type' => 'money'], $this->methods()),
            ['label' => 'Top-up deposit', 'type' => 'money'],
            ['label' => 'Refund', 'type' => 'money'],
            ['label' => 'Kas masuk bersih', 'type' => 'money'],
        ];
    }

    public function rows(): array
    {
        $from = $this->filters->from->toDateString();
        $to = $this->filters->to->toDateString();
        $branches = $this->filters->branchIds;

        $payments = DB::table('payments')->join('payment_transactions', 'payment_transactions.id', '=', 'payments.payment_transaction_id')
            ->whereIn('payment_transactions.branch_id', $branches)->whereDate('payment_transactions.paid_at', '>=', $from)->whereDate('payment_transactions.paid_at', '<=', $to)
            ->where('payments.status', '!=', PaymentStatus::Void->value)->where('payments.method', '!=', PaymentMethod::Deposit->value)
            ->get(['payment_transactions.paid_at', 'payments.method', 'payments.amount']);
        $topups = DB::table('deposit_transactions')->whereIn('branch_id', $branches)->where('type', DepositTransactionType::TopUp->value)
            ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->get(['created_at', 'amount']);
        $refunds = DB::table('refunds')->whereIn('branch_id', $branches)->where('status', RefundStatus::Paid->value)
            ->where('method', '!=', PaymentMethod::Deposit->value)->whereDate('paid_at', '>=', $from)->whereDate('paid_at', '<=', $to)->get(['paid_at', 'amount']);

        $days = [];
        $day = fn ($value): string => substr((string) $value, 0, 10);

        foreach ($payments as $p) {
            $days[$day($p->paid_at)]['m'][$p->method] = ($days[$day($p->paid_at)]['m'][$p->method] ?? 0) + (int) $p->amount;
        }

        foreach ($topups as $t) {
            $days[$day($t->created_at)]['topup'] = ($days[$day($t->created_at)]['topup'] ?? 0) + (int) $t->amount;
        }

        foreach ($refunds as $r) {
            $days[$day($r->paid_at)]['refund'] = ($days[$day($r->paid_at)]['refund'] ?? 0) + (int) $r->amount;
        }

        ksort($days);
        $rows = [];

        foreach ($days as $date => $data) {
            $methods = array_map(fn (PaymentMethod $m): int => (int) ($data['m'][$m->value] ?? 0), $this->methods());
            $topup = (int) ($data['topup'] ?? 0);
            $refund = (int) ($data['refund'] ?? 0);
            $rows[] = [date('d/m/Y', (int) strtotime($date)), ...$methods, $topup, -$refund, array_sum($methods) + $topup - $refund];
        }

        return $rows;
    }

    public function totals(): ?array
    {
        return $this->sumColumns($this->rows(), range(1, count($this->columns()) - 1));
    }
}
