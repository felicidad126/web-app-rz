<?php

namespace App\Console\Commands;

use App\Models\Practitioner;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Kirim notifikasi (in-app) ke Owner & Admin bila ada SIP yang hampir/sudah kedaluwarsa. */
#[Signature('clinic:check-sip-expiry')]
#[Description('Kirim peringatan SIP kedaluwarsa ke Owner & Admin')]
class CheckSipExpiry extends Command
{
    public function handle(): int
    {
        $days = (int) config('clinic.sip_expiry_warning_days');

        $practitioners = Practitioner::query()->with('user')->sipExpiringWithin($days)->orderBy('sip_expired_at')->get();

        if ($practitioners->isEmpty()) {
            $this->info('Tidak ada SIP yang perlu diperingatkan.');

            return self::SUCCESS;
        }

        $lines = $practitioners->map(fn (Practitioner $p): string => sprintf(
            '%s — %s',
            $p->user->name,
            $p->isSipExpired() ? 'SUDAH kedaluwarsa '.$p->sip_expired_at->format('d/m/Y') : 'berlaku s.d. '.$p->sip_expired_at->format('d/m/Y'),
        ));

        $recipients = User::role(['owner', 'admin'])->where('is_active', true)->get();

        Notification::make()
            ->title('Peringatan masa berlaku SIP')
            ->body($lines->join("\n"))
            ->warning()
            ->sendToDatabase($recipients);

        $this->info("Peringatan dikirim ke {$recipients->count()} pengguna untuk {$practitioners->count()} nakes.");

        return self::SUCCESS;
    }
}
