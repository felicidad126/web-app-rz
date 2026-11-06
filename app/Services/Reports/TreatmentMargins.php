<?php

namespace App\Services\Reports;

use App\Enums\CommissionStatus;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\VisitStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Omzet & margin per tindakan (Kpi::Margin): setelah diskon − bahan − lab − jasa dokter. */
final class TreatmentMargins
{
    /**
     * @param  list<int>  $branchIds
     * @return Collection<int, \stdClass> treatment_id, name, qty, revenue, material, lab, commission, margin
     */
    public static function between(Carbon $from, Carbon $to, array $branchIds, ?int $practitionerId = null): Collection
    {
        $commissions = DB::table('commissions')->where('status', '!=', CommissionStatus::Cancelled->value)
            ->groupBy('invoice_item_id')->selectRaw('invoice_item_id, sum(amount) as amount');

        return DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('visits', 'visits.id', '=', 'invoices.visit_id')
            ->join('treatments', 'treatments.id', '=', 'invoice_items.treatment_id')
            ->leftJoinSub($commissions, 'c', 'c.invoice_item_id', '=', 'invoice_items.id')
            ->where('invoice_items.item_type', InvoiceItemType::Procedure->value)
            ->where('invoices.status', '!=', InvoiceStatus::Void->value)
            ->whereIn('visits.status', [VisitStatus::Done->value, VisitStatus::Billed->value])
            ->whereIn('visits.branch_id', $branchIds)
            ->whereDate('visits.visit_date', '>=', $from->toDateString())->whereDate('visits.visit_date', '<=', $to->toDateString())
            ->when($practitionerId !== null, fn ($q) => $q->where('invoice_items.practitioner_id', $practitionerId))
            ->groupBy('invoice_items.treatment_id', 'treatments.name')
            ->selectRaw('invoice_items.treatment_id, treatments.name, sum(invoice_items.qty) as qty,
                sum(invoice_items.net_amount + invoice_items.package_value) as revenue, sum(invoice_items.material_cost) as material,
                sum(invoice_items.lab_cost) as lab, coalesce(sum(c.amount), 0) as commission')
            ->get()
            ->map(function ($row) {
                foreach (['revenue', 'material', 'lab', 'commission'] as $field) {
                    $row->{$field} = (int) $row->{$field};
                }
                $row->qty = (float) $row->qty;
                $row->margin = $row->revenue - $row->material - $row->lab - $row->commission;

                return $row;
            })
            ->sortByDesc('revenue')->values();
    }
}
