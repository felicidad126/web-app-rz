<?php

namespace App\Console\Commands;

use App\Services\Reports\DailyMetrics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('metrics:snapshot {date? : Tanggal (Y-m-d), default kemarin} {--from= : Isi mundur mulai tanggal ini s.d. kemarin} {--force : Hitung ulang snapshot yang sudah ada}')]
#[Description('Simpan snapshot KPI harian (daily_metrics) per cabang & dokter')]
class SnapshotMetrics extends Command
{
    public function handle(): int
    {
        $to = $this->argument('date') ? Carbon::parse($this->argument('date')) : today()->subDay();
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : $to->copy();

        if ($to->gte(today())) {
            $this->error('Snapshot hanya untuk hari yang sudah lewat. Hari ini dihitung langsung di dashboard.');

            return self::FAILURE;
        }

        $written = 0;

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $written += DailyMetrics::snapshot($day->copy(), null, (bool) $this->option('force'));
        }

        $this->info("{$written} snapshot (tanggal × cabang) ditulis untuk {$from->toDateString()} s.d. {$to->toDateString()}.");

        return self::SUCCESS;
    }
}
