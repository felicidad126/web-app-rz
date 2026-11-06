<?php

namespace App\Reports;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;

/** Daftar janji yang tidak datang (untuk ditindaklanjuti), dengan nomor HP. */
class NoShowReport extends Report
{
    public static function key(): string
    {
        return 'tidak-datang';
    }

    public static function label(): string
    {
        return 'Tidak datang';
    }

    public static function description(): string
    {
        return 'Janji berstatus "tidak datang" menurut tanggal janji.';
    }

    public static function canView(User $user): bool
    {
        return $user->can('View:FinancialReports');
    }

    public function columns(): array
    {
        return [
            ['label' => 'Tanggal', 'type' => 'text'], ['label' => 'Jam', 'type' => 'text'], ['label' => 'Pasien', 'type' => 'text'],
            ['label' => 'No. HP', 'type' => 'text'], ['label' => 'Dokter', 'type' => 'text'], ['label' => 'Cabang', 'type' => 'text'], ['label' => 'Keperluan', 'type' => 'text'],
        ];
    }

    public function rows(): array
    {
        return Appointment::query()->with(['patient', 'practitioner.user', 'branch'])->where('status', AppointmentStatus::NoShow)
            ->whereIn('branch_id', $this->filters->branchIds)
            ->whereDate('start_at', '>=', $this->filters->from)->whereDate('start_at', '<=', $this->filters->to)
            ->orderBy('start_at')->get()
            ->map(fn (Appointment $a): array => [
                $a->start_at->format('d/m/Y'), $a->start_at->format('H:i'), $a->patient->name, (string) $a->patient->phone,
                $a->practitioner->displayName(), $a->branch->name, (string) $a->reason,
            ])->all();
    }
}
