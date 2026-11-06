<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Jadwal (Asia/Jakarta). Aktifkan di server dengan cron:
|   * * * * * cd /path/ke/app && php artisan schedule:run >> /dev/null 2>&1
|--------------------------------------------------------------------------
*/

Schedule::command('backup:clean')->dailyAt('01:00')->onOneServer();
Schedule::command('backup:run')->dailyAt('01:30')->onOneServer();
Schedule::command('backup:monitor')->dailyAt('07:00')->onOneServer();

Schedule::command('clinic:check-sip-expiry')->weeklyOn(1, '07:30')->onOneServer();

// Bersihkan data import/export Filament & job batch lama.
Schedule::command('queue:prune-batches --hours=168')->daily();
Schedule::command('queue:prune-failed --hours=720')->daily();

// Fase 4: kirim ulang kiriman SATUSEHAT yang gagal karena gangguan (masalah data menunggu perbaikan staf).
Schedule::command('satusehat:retry')->hourlyAt(17)->onOneServer()->withoutOverlapping();

// Fase 6: snapshot KPI harian (angka kemarin dibekukan) dan ringkasan harian untuk owner.
Schedule::command('metrics:snapshot')->dailyAt('00:30')->onOneServer()->withoutOverlapping();
Schedule::command('reports:daily-summary')->dailyAt('21:00')->onOneServer();
