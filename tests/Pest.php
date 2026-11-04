<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/** Login sebagai pengguna dengan peran tertentu (MFA sudah diatur). */
function loginAs(string $role): User
{
    $user = User::factory()->withMfa()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    return $user;
}
