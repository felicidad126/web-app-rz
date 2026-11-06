<?php

namespace App\Services\Payroll;

use App\Enums\PayoutStatus;
use App\Models\EmployeeSalaryItem;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Slip gaji staf (MVP, Planning §5.7): komponen tetap per karyawan disalin ke slip bulanan, lalu bisa diubah
 * selama draf (lembur, kasbon, PPh 21 manual). Final mengunci slip; dibayar menandai tanggal bayar.
 */
final class Payslips
{
    /** @return Collection<int, Payslip> slip baru (yang sudah ada tidak diubah) */
    public static function generate(Carbon $period, User $user): Collection
    {
        self::ensureCanManage($user);
        $start = $period->copy()->startOfMonth();

        $userIds = EmployeeSalaryItem::query()->whereHas('user', fn ($q) => $q->where('is_active', true))->distinct()->pluck('user_id');
        $existing = Payslip::query()->whereDate('period', $start)->pluck('user_id');

        return $userIds->diff($existing)->map(fn ($id): Payslip => DB::transaction(function () use ($id, $start): Payslip {
            $payslip = Payslip::create(['user_id' => $id, 'period' => $start->toDateString(), 'status' => PayoutStatus::Draft]);
            self::fillFromTemplate($payslip);

            return $payslip;
        }))->values();
    }

    /** Isi ulang baris dari komponen tetap karyawan (hanya draf). */
    public static function fillFromTemplate(Payslip $payslip): void
    {
        self::ensureDraft($payslip);

        DB::transaction(function () use ($payslip): void {
            $payslip->lines()->get()->each->delete();

            $items = EmployeeSalaryItem::query()->with('component')->where('user_id', $payslip->user_id)->get()
                ->filter(fn (EmployeeSalaryItem $item): bool => $item->component->is_active)
                ->sortBy(fn (EmployeeSalaryItem $item): int => $item->component->sort);

            foreach ($items->values() as $index => $item) {
                $payslip->lines()->create([
                    'salary_component_id' => $item->salary_component_id,
                    'kind' => $item->component->kind,
                    'name' => $item->component->name,
                    'amount' => $item->amount,
                    'sort' => $index + 1,
                ]);
            }

            $payslip->recalculate();
        });
    }

    public static function finalize(Payslip $payslip, User $user): void
    {
        self::ensureCanManage($user);
        self::ensureDraft($payslip);
        $payslip->recalculate();

        if ($payslip->net_total < 0) {
            throw new InvalidArgumentException('Potongan melebihi pendapatan. Periksa baris slip.');
        }

        $payslip->forceFill(['status' => PayoutStatus::Final, 'finalized_by' => $user->id, 'finalized_at' => now()])->save();
    }

    public static function reopen(Payslip $payslip, User $user): void
    {
        self::ensureCanManage($user);

        if ($payslip->status !== PayoutStatus::Final) {
            throw new InvalidArgumentException('Hanya slip final (belum dibayar) yang bisa dibuka kembali.');
        }

        $payslip->forceFill(['status' => PayoutStatus::Draft, 'finalized_by' => null, 'finalized_at' => null])->save();
    }

    public static function markPaid(Payslip $payslip, User $user): void
    {
        self::ensureCanManage($user);

        if ($payslip->status !== PayoutStatus::Final) {
            throw new InvalidArgumentException('Finalkan slip dulu sebelum ditandai dibayar.');
        }

        $payslip->forceFill(['status' => PayoutStatus::Paid, 'paid_by' => $user->id, 'paid_at' => now()])->save();
    }

    private static function ensureDraft(Payslip $payslip): void
    {
        if (! $payslip->isDraft()) {
            throw new InvalidArgumentException('Slip '.$payslip->periodLabel().' sudah '.$payslip->status->getLabel().'.');
        }
    }

    private static function ensureCanManage(User $user): void
    {
        if (! $user->can('Manage:Payroll')) {
            throw new InvalidArgumentException('Hanya owner yang bisa mengelola gaji.');
        }
    }
}
