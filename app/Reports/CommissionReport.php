<?php

namespace App\Reports;

use App\Enums\CommissionStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Jasa dokter per dokter menurut tanggal tindakan, dengan status (menunggu / diakui / dibayar). */
class CommissionReport extends Report
{
    public static function key(): string
    {
        return 'jasa';
    }

    public static function label(): string
    {
        return 'Jasa dokter';
    }

    public static function description(): string
    {
        return 'Entri jasa menurut tanggal tindakan, termasuk entri pembalik dari void/refund. Rincian per tindakan ada di menu Jasa & Gaji.';
    }

    public static function canView(User $user): bool
    {
        return ($user->can('View:FinancialReports') && $user->can('ViewAll:Commission')) || ($user->can('View:Reports') && self::isDoctor($user));
    }

    public static function supportsPractitioner(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            ['label' => 'Dokter / nakes', 'type' => 'text'], ['label' => 'Tindakan', 'type' => 'number'], ['label' => 'Bruto', 'type' => 'money'],
            ['label' => 'Diskon', 'type' => 'money'], ['label' => 'Bahan', 'type' => 'money'], ['label' => 'Lab', 'type' => 'money'],
            ['label' => 'Basis', 'type' => 'money'], ['label' => 'Menunggu lunas', 'type' => 'money'], ['label' => 'Diakui', 'type' => 'money'],
            ['label' => 'Dibayar', 'type' => 'money'], ['label' => 'Total jasa', 'type' => 'money'],
        ];
    }

    public function rows(): array
    {
        $pending = CommissionStatus::Pending->value;
        $earned = CommissionStatus::Earned->value;
        $paid = CommissionStatus::Paid->value;

        return DB::table('commissions')->join('practitioners', 'practitioners.id', '=', 'commissions.practitioner_id')
            ->join('users', 'users.id', '=', 'practitioners.user_id')
            ->whereIn('commissions.branch_id', $this->filters->branchIds)
            ->where('commissions.status', '!=', CommissionStatus::Cancelled->value)
            ->whereDate('commissions.service_date', '>=', $this->filters->from->toDateString())
            ->whereDate('commissions.service_date', '<=', $this->filters->to->toDateString())
            ->when($this->filters->practitionerId !== null, fn ($q) => $q->where('commissions.practitioner_id', $this->filters->practitionerId))
            ->groupBy('commissions.practitioner_id', 'users.name')
            ->selectRaw("users.name, sum(case when commissions.reverses_commission_id is null then 1 else 0 end) as n, sum(gross_amount) as gross,
                sum(discount_amount) as discount, sum(material_cost) as material, sum(lab_cost) as lab, sum(base_amount) as base,
                sum(case when commissions.status = '{$pending}' then amount else 0 end) as pending,
                sum(case when commissions.status = '{$earned}' then amount else 0 end) as earned,
                sum(case when commissions.status = '{$paid}' then amount else 0 end) as paid, sum(amount) as total")
            ->orderBy('users.name')->get()
            ->map(fn ($r): array => [$r->name, (int) $r->n, (int) $r->gross, (int) $r->discount, (int) $r->material, (int) $r->lab, (int) $r->base,
                (int) $r->pending, (int) $r->earned, (int) $r->paid, (int) $r->total])->all();
    }

    public function totals(): ?array
    {
        return $this->sumColumns($this->rows(), range(1, 10));
    }

    public static function ownOnly(User $user): bool
    {
        return ! $user->can('ViewAll:Commission');
    }
}
