<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/** Buat akun Owner pertama saat instalasi (MFA wajib diatur saat login pertama). */
#[Signature('clinic:create-owner {--name=} {--email=} {--password=} {--check : Hanya cek: exit 0 jika sudah ada owner (selain akun demo), 1 jika belum}')]
#[Description('Buat akun Owner klinik')]
class CreateOwner extends Command
{
    public function handle(): int
    {
        if ($this->option('check')) {
            return $this->ownerExists() ? self::SUCCESS : self::FAILURE;
        }

        $data = [
            'name' => $this->option('name') ?? text('Nama', required: true),
            'email' => $this->option('email') ?? text('Email', required: true),
            'password' => $this->option('password') ?? password('Password (min. 10 karakter, huruf + angka)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->callSilently('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        $user = User::create([...$data, 'is_active' => true]);
        $user->assignRole('owner');

        $this->info("Owner {$user->email} dibuat. Atur MFA saat login pertama.");

        return self::SUCCESS;
    }

    private function ownerExists(): bool
    {
        if (! Role::where('name', 'owner')->exists()) {
            return false;
        }

        return User::role('owner')->where('email', 'not like', '%@demo.test')->exists();
    }
}
