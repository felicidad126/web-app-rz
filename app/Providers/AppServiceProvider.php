<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Services\SatuSehat\SatuSehatClient;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Dibaca dari config setiap kali dibutuhkan (kredensial ada di .env server).
        $this->app->bind(SatuSehatClient::class, fn (): SatuSehatClient => SatuSehatClient::fromConfig());
    }

    public function boot(): void
    {
        // Lazy loading & atribut hilang jadi exception hanya saat development/test (menangkap N+1).
        // Staging berperilaku seperti produksi.
        Model::shouldBeStrict($this->app->environment('local', 'testing'));

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        $this->forceHttpsWhenConfigured();

        // Model vendor: policy hasil Shield (app/Policies) tidak ditemukan otomatis oleh Laravel.
        if (class_exists(ActivityPolicy::class)) {
            Gate::policy(Activity::class, ActivityPolicy::class);
        }

        $this->configureFilamentDefaults();

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }

    /**
     * Bawaan tampilan panel (Fondasi UI mockup v2):
     * - format tanggal & angka Indonesia di tabel dan infolist;
     * - teal hanya untuk aksi utama: tombol Ubah/Hapus di baris & kepala halaman netral, merah hanya di konfirmasi hapus;
     * - nilai ya/tidak abu-abu (bukan hijau/merah), ikon tabel kosong bukan tanda silang.
     */
    private function configureFilamentDefaults(): void
    {
        Number::useLocale('id');

        Table::configureUsing(fn (Table $table): Table => $table
            ->defaultDateDisplayFormat('d/m/Y')
            ->defaultDateTimeDisplayFormat('d/m/Y H:i')
            ->defaultNumberLocale('id')
            ->emptyStateIcon(Heroicon::OutlinedInbox)
            ->filtersTriggerAction(fn (Action $action): Action => $action->badgeColor('gray')));

        Schema::configureUsing(fn (Schema $schema): Schema => $schema
            ->defaultDateDisplayFormat('d/m/Y')
            ->defaultDateTimeDisplayFormat('d/m/Y H:i')
            ->defaultNumberLocale('id'));

        EditAction::configureUsing(fn (EditAction $action): EditAction => $action->color('gray'));

        DeleteAction::configureUsing(fn (DeleteAction $action): DeleteAction => $action
            ->color('gray')
            ->modalSubmitAction(fn (Action $submit): Action => $submit->color('danger')));

        IconColumn::configureUsing(fn (IconColumn $column): IconColumn => $column
            ->trueColor('gray')
            ->falseColor('gray')
            ->falseIcon(Heroicon::OutlinedMinus));
    }

    /**
     * Jika APP_URL memakai https (domain bersertifikat atau Tailscale), semua link & redirect
     * dibuat https, walau header X-Forwarded-Proto dari reverse proxy tidak sampai ke aplikasi.
     */
    public function forceHttpsWhenConfigured(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
