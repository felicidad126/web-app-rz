<?php

namespace App\Console\Commands;

use App\Enums\SatuSehatSyncStatus;
use App\Models\SatuSehatSync;
use App\Models\Visit;
use App\Services\SatuSehat\VisitSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Jaring pengaman: kunjungan yang gagal karena gangguan (bukan masalah data) dan sudah melewati batas
 * percobaan job dimasukkan lagi ke antrean. Dijalankan terjadwal tiap jam.
 */
#[Signature('satusehat:retry')]
#[Description('Antrekan ulang kiriman SATUSEHAT yang gagal karena gangguan jaringan/server')]
class SatuSehatRetry extends Command
{
    public function handle(): int
    {
        if (! VisitSync::enabled()) {
            return self::SUCCESS;
        }

        $count = 0;

        SatuSehatSync::query()
            ->where('status', SatuSehatSyncStatus::Failed)
            ->where('syncable_type', (new Visit)->getMorphClass())
            ->where(fn ($q) => $q->whereNull('last_attempt_at')->orWhere('last_attempt_at', '<', now()->subHour()))
            ->each(function (SatuSehatSync $sync) use (&$count): void {
                if ($visit = Visit::find($sync->syncable_id)) {
                    VisitSync::queue($visit);
                    $count++;
                }
            });

        $this->info("{$count} kiriman diantrekan ulang.");

        return self::SUCCESS;
    }
}
