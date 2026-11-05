<?php

namespace App\Services\Billing;

use App\Enums\InstallmentStatus;
use App\Enums\PatientPackageStatus;
use App\Models\Package;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Services\NumberSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Penjualan paket (mis. ortodonti): kuota tindakan di-snapshot dan jadwal cicilan dibuat.
 *
 * Cicilan: DP jatuh tempo di tanggal mulai; sisanya dibagi rata (dibulatkan ke bawah per Rp1.000,
 * selisihnya masuk cicilan terakhir) dan jatuh tempo setiap tanggal {due_day} bulan berikutnya.
 */
final class PackageSales
{
    /**
     * @param  array{branch_id: int, practitioner_id?: int|null, price?: int|null, down_payment?: int|null, installment_count?: int|null, start_date?: mixed, due_day?: int|null, notes?: string|null}  $data
     */
    public static function sell(Patient $patient, Package $package, array $data): PatientPackage
    {
        $price = (int) ($data['price'] ?? $package->price);
        $downPayment = (int) ($data['down_payment'] ?? 0);
        $count = (int) ($data['installment_count'] ?? 0);
        $start = Carbon::parse($data['start_date'] ?? today())->startOfDay();
        $dueDay = (int) ($data['due_day'] ?? min(28, $start->day));

        if ($price <= 0 || $downPayment < 0 || $downPayment > $price) {
            throw new InvalidArgumentException('Uang muka harus antara 0 dan harga paket.');
        }

        if ($count === 0 && $downPayment !== $price) {
            throw new InvalidArgumentException('Tanpa cicilan, uang muka harus sama dengan harga paket (bayar lunas).');
        }

        if ($dueDay < 1 || $dueDay > 28) {
            throw new InvalidArgumentException('Tanggal jatuh tempo cicilan harus 1–28.');
        }

        $package->loadMissing('items');

        if ($package->items->isEmpty()) {
            throw new InvalidArgumentException('Paket belum punya isi tindakan.');
        }

        return DB::transaction(function () use ($patient, $package, $data, $price, $downPayment, $count, $start, $dueDay): PatientPackage {
            $patientPackage = PatientPackage::create([
                'package_number' => NumberSequence::nextPackageNumber($start),
                'patient_id' => $patient->id,
                'package_id' => $package->id,
                'branch_id' => $data['branch_id'],
                'practitioner_id' => $data['practitioner_id'] ?? null,
                'name' => $package->name,
                'price' => $price,
                'down_payment' => $downPayment,
                'installment_count' => $count,
                'start_date' => $start,
                'due_day' => $dueDay,
                'expires_at' => $package->validity_days ? $start->copy()->addDays($package->validity_days) : null,
                'status' => PatientPackageStatus::Active,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($package->items as $item) {
                $patientPackage->items()->create(['treatment_id' => $item->treatment_id, 'quota' => $item->quota]);
            }

            foreach (self::schedule($price, $downPayment, $count, $start, $dueDay) as $row) {
                $patientPackage->installments()->create([...$row, 'status' => InstallmentStatus::Pending]);
            }

            return $patientPackage;
        });
    }

    /**
     * @return list<array{sequence: int, due_date: Carbon, amount: int}>
     */
    public static function schedule(int $price, int $downPayment, int $count, Carbon $start, int $dueDay): array
    {
        $rows = [];

        if ($downPayment > 0) {
            $rows[] = ['sequence' => 0, 'due_date' => $start->copy(), 'amount' => $downPayment];
        }

        $rest = $price - $downPayment;

        if ($count < 1 || $rest <= 0) {
            return $rows;
        }

        $base = intdiv(intdiv($rest, $count), 1000) * 1000;

        if ($base === 0) {
            $base = intdiv($rest, $count);
        }

        for ($i = 1; $i <= $count; $i++) {
            $due = $start->copy()->startOfMonth()->addMonthsNoOverflow($i)->setDay($dueDay);
            $amount = $i === $count ? $rest - $base * ($count - 1) : $base;
            $rows[] = ['sequence' => $i, 'due_date' => $due, 'amount' => $amount];
        }

        return $rows;
    }

    /** Batalkan paket: cicilan yang belum ditagih ikut batal. Tagihan yang sudah terbit di-void lewat persetujuan. */
    public static function cancel(PatientPackage $package, string $reason): void
    {
        DB::transaction(function () use ($package, $reason): void {
            $package->installments()->where('status', InstallmentStatus::Pending)->update(['status' => InstallmentStatus::Cancelled]);
            $package->update([
                'status' => PatientPackageStatus::Cancelled,
                'notes' => trim(($package->notes ? $package->notes."\n" : '').'Dibatalkan: '.$reason),
            ]);
        });
    }

    /** Tandai selesai bila semua cicilan lunas dan semua kuota terpakai. */
    public static function completeIfDone(PatientPackage $package): void
    {
        if ($package->status !== PatientPackageStatus::Active) {
            return;
        }

        $unpaid = $package->installments()->whereNotIn('status', [InstallmentStatus::Paid, InstallmentStatus::Cancelled])->exists();
        $quotaLeft = array_sum($package->remainingQuota()) > 0;

        if (! $unpaid && ! $quotaLeft) {
            $package->update(['status' => PatientPackageStatus::Completed]);
        }
    }
}
