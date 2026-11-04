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
