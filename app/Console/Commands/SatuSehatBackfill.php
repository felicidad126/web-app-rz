<?php

namespace App\Console\Commands;

use App\Filament\Pages\SatuSehatMonitor;
use App\Services\SatuSehat\VisitSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Antrekan kunjungan bertanda tangan yang belum diterima SATUSEHAT (mis. sejak soft launch). */
#[Signature('satusehat:backfill {since : Tanggal awal kunjungan, YYYY-MM-DD}')]
#[Description('Kirim kunjungan lama yang belum terkirim ke SATUSEHAT')]
class SatuSehatBackfill extends Command
{
    public function handle(): int
    {
        if (! VisitSync::enabled()) {
            $this->error('SATUSEHAT_ENABLED belum true di .env.');

            return self::FAILURE;
        }

        $count = SatuSehatMonitor::backfill(Carbon::parse((string) $this->argument('since'))->startOfDay());
        $this->info("{$count} kunjungan masuk antrean SATUSEHAT.");

        return self::SUCCESS;
    }
}
