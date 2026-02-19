<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;

it('seeds an admin user with expected credentials and no default test user', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->is_admin)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull();

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

it('admin user seeder is idempotent', function () {
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::query()->where('email', 'admin@example.com')->count())->toBe(1);
});
