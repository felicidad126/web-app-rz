<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\ActivityPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
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
