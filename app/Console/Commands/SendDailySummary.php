<?php

namespace App\Console\Commands;

use App\Mail\DailySummaryMail;
use App\Models\User;
use App\Services\Reports\DailySummary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

#[Signature('reports:daily-summary {--date= : Tanggal (Y-m-d), default hari ini}')]
#[Description('Kirim ringkasan harian (produksi, kas, kunjungan, pasien baru, tidak datang, stok kritis) ke email owner')]
class SendDailySummary extends Command
{
    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        $owners = User::role('owner')->where('is_active', true)->whereNotNull('email')->get();

        if ($owners->isEmpty()) {
            $this->warn('Tidak ada owner aktif dengan email.');

            return self::SUCCESS;
        }

        $summary = DailySummary::build($date);

        foreach ($owners as $owner) {
            Mail::to($owner)->send(new DailySummaryMail($summary));
        }

        $this->info('Ringkasan '.$date->toDateString().' dikirim ke '.$owners->count().' owner.');

        return self::SUCCESS;
    }
}
