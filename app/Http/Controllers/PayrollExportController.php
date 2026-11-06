<?php

namespace App\Http\Controllers;

use App\Enums\SalaryComponentKind;
use App\Models\Commission;
use App\Models\CommissionPayout;
use App\Models\Payslip;
use App\Models\User;
use App\Support\CommissionScope;
use App\Support\Spreadsheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Slip jasa dokter & slip gaji (PDF A4), serta rekap bulanan dalam Excel. */
class PayrollExportController extends Controller
{
    public function commissionSlip(CommissionPayout $payout): Response
    {
        $user = $this->user();
        abort_unless($user->can('View:CommissionPayout') && CommissionScope::canViewPractitioner($user, $payout->practitioner_id), 403);

        $payout->loadMissing(['practitioner.user', 'finalizer', 'adjustments', 'attendances.branch', 'commissions.invoice.patient', 'commissions.invoiceItem']);

        return Pdf::loadView('pdf.commission-slip', ['payout' => $payout])->setPaper('a4')
            ->stream('slip-jasa-'.$payout->period->format('Y-m').'-'.$payout->practitioner_id.'.pdf');
    }

    public function commissionExcel(string $period): BinaryFileResponse
    {
        $user = $this->user();
        abort_unless($user->can('ViewAny:CommissionPayout'), 403);
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();

        $payouts = CommissionScope::apply(CommissionPayout::query())->whereDate('period', $start)
            ->with(['practitioner.user', 'commissions.invoice.patient', 'commissions.invoiceItem'])->get()
            ->sortBy(fn (CommissionPayout $p): string => $p->practitioner->displayName());

        $summary = $payouts->map(fn (CommissionPayout $p): array => [
            $p->practitioner->displayName(),
            $p->commissions->whereNull('reverses_commission_id')->count(),
            (int) $p->commissions->sum('gross_amount'),
            (int) $p->commissions->sum('discount_amount'),
            (int) $p->commissions->sum('material_cost'),
            (int) $p->commissions->sum('lab_cost'),
            (int) $p->commissions->sum('base_amount'),
            $p->commission_total,
            $p->allowance_total,
            $p->adjustment_total,
            $p->total,
            $p->status->getLabel(),
        ]);

        $lines = $payouts->flatMap(fn (CommissionPayout $p) => $p->commissions->map(fn (Commission $c): array => [
            $c->service_date->format('Y-m-d'),
            $p->practitioner->displayName(),
            $c->role->getLabel(),
            $c->invoice->patient->name,
            $c->invoice->label(),
            $c->invoiceItem->description,
            $c->gross_amount,
            $c->discount_amount,
            $c->material_cost,
            $c->lab_cost,
            $c->base_amount,
            (string) ($c->rule_snapshot['summary'] ?? ''),
            $c->amount,
            $c->isReversal() ? 'Pembalik: '.$c->reason : '',
        ]));

        return Spreadsheet::download('jasa-dokter-'.$period.'.xlsx', [
            'Rekap '.$period => [
                'headers' => ['Dokter', 'Tindakan', 'Produksi bruto', 'Diskon', 'Bahan', 'Biaya lab', 'Basis', 'Jasa', 'Hadir/on call', 'Tambahan/potongan', 'Diterima', 'Status'],
                'rows' => $summary,
            ],
            'Rincian' => [
                'headers' => ['Tanggal', 'Dokter', 'Peran', 'Pasien', 'No. tagihan', 'Tindakan', 'Bruto', 'Diskon', 'Bahan', 'Lab', 'Basis', 'Aturan', 'Jasa', 'Keterangan'],
                'rows' => $lines,
            ],
        ]);
    }

    public function payslipPdf(Payslip $payslip): Response
    {
        abort_unless($this->user()->can('View:Payslip'), 403);
        $payslip->loadMissing(['user', 'lines']);

        return Pdf::loadView('pdf.payslip', ['payslip' => $payslip])->setPaper('a5')
            ->stream('slip-gaji-'.$payslip->period->format('Y-m').'-'.$payslip->user_id.'.pdf');
    }

    public function payslipExcel(string $period): BinaryFileResponse
    {
        abort_unless($this->user()->can('ViewAny:Payslip'), 403);
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $payslips = Payslip::query()->whereDate('period', $start)->with(['user', 'lines'])->get()->sortBy(fn (Payslip $p): string => $p->user->name);

        $earnings = $payslips->flatMap(fn (Payslip $p) => $p->lines->where('kind', SalaryComponentKind::Earning)->pluck('name'))->unique()->values();
        $deductions = $payslips->flatMap(fn (Payslip $p) => $p->lines->where('kind', SalaryComponentKind::Deduction)->pluck('name'))->unique()->values();

        $rows = $payslips->map(fn (Payslip $p): array => [
            $p->user->name,
            ...$earnings->map(fn (string $name): int => (int) $p->lines->where('kind', SalaryComponentKind::Earning)->where('name', $name)->sum('amount'))->all(),
            $p->earning_total,
            ...$deductions->map(fn (string $name): int => (int) $p->lines->where('kind', SalaryComponentKind::Deduction)->where('name', $name)->sum('amount'))->all(),
            $p->deduction_total,
            $p->net_total,
            $p->status->getLabel(),
        ]);

        return Spreadsheet::download('gaji-'.$period.'.xlsx', [
            'Gaji '.$period => [
                'headers' => ['Karyawan', ...$earnings->all(), 'Total pendapatan', ...$deductions->all(), 'Total potongan', 'Diterima', 'Status'],
                'rows' => $rows,
            ],
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->is_active, 403);

        return $user;
    }
}
