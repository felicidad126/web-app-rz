<?php

namespace App\Jobs;

use App\Models\Visit;
use App\Services\SatuSehat\SatuSehatException;
use App\Services\SatuSehat\VisitSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kirim satu kunjungan (yang rekam medisnya sudah ditandatangani) ke SATUSEHAT.
 *
 * Gangguan jaringan/server dicoba ulang dengan jeda bertambah (1 menit … 6 jam).
 * Masalah data (NIK kosong, kode belum dipetakan, ditolak validasi) tidak diulang otomatis:
 * statusnya "Perlu diperbaiki" sampai staf memperbaiki lalu menekan "Kirim ulang".
 */
class SendVisitToSatuSehat implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $visitId)
    {
        $this->onQueue((string) config('satusehat.queue', 'default'));
    }

    /** @return list<int> detik */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 21600];
    }

    public function uniqueId(): string
    {
        return 'visit-'.$this->visitId;
    }

    public function handle(VisitSync $sync): void
    {
        $visit = Visit::find($this->visitId);

        if ($visit === null) {
            return;
        }

        try {
            $sync->send($visit);
        } catch (SatuSehatException $e) {
            // Hanya gangguan sementara yang dilempar ulang agar queue mencoba lagi.
            if ($e->isTransient()) {
                throw $e;
            }
        }
    }
}
