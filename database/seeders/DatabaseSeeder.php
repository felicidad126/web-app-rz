<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder dasar untuk semua lingkungan. Data demo terpisah: `php artisan db:seed --class=DemoSeeder`.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            ReferenceDataSeeder::class,
            CommissionRuleSeeder::class,
        ]);
    }
}
