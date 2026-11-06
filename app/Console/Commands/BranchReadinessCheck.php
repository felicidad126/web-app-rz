<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\Clinic\BranchReadiness;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Daftar periksa rollout cabang di konsol. Gagal (exit 1) bila ada butir wajib yang belum terpenuhi. */
#[Signature('clinic:branch-readiness {branch? : Kode cabang (bawaan: semua cabang aktif)}')]
#[Description('Periksa kesiapan cabang sebelum mulai memakai aplikasi')]
class BranchReadinessCheck extends Command
{
    public function handle(): int
    {
        $code = $this->argument('branch');
        $branches = Branch::query()
            ->when($code, fn ($q) => $q->where('code', $code), fn ($q) => $q->where('is_active', true))
            ->orderBy('code')->get();

        if ($branches->isEmpty()) {
            $this->error($code ? "Cabang {$code} tidak ditemukan." : 'Belum ada cabang aktif.');

            return self::FAILURE;
        }

        $allReady = true;

        foreach ($branches as $branch) {
            $readiness = new BranchReadiness($branch);
            $ready = $readiness->ready();
            $allReady = $allReady && $ready;

            $this->newLine();
            $this->line("<options=bold>{$branch->code} · {$branch->name}</> — ".($ready ? '<fg=green>SIAP</>' : '<fg=red>BELUM SIAP</>'));
            $this->table(['', 'Butir', 'Tingkat', 'Keterangan'], array_map(fn (array $c): array => [
                $c['ok'] ? 'OK' : ($c['level'] === BranchReadiness::REQUIRED ? 'X' : '!'),
                $c['label'], $c['level'], $c['detail'],
            ], $readiness->checks()));
        }

        return $allReady ? self::SUCCESS : self::FAILURE;
    }
}
