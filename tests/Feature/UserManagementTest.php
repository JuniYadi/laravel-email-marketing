<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('requires authentication for users management page', function () {
    $this->get(route('users.index'))
        ->assertRedirect(route('login'));
});

it('returns forbidden for non-admin users on users management page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('users.index'))
        ->assertForbidden();
});

it('allows admin users to view users management page', function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));

    $this->get(route('users.index'))
        ->assertSuccessful()
        ->assertSee('Users');
});

it('allows admin users to edit another user including password and admin flag', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $target = User::factory()->create([
        'name' => 'Regular User',
        'email' => 'regular@example.com',
        'is_admin' => false,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->call('openEditUserModal', $target->id)
        ->set('editUserName', 'Updated User')
        ->set('editUserEmail', 'updated@example.com')
        ->set('editUserPassword', 'new-password')
        ->set('editUserPasswordConfirmation', 'new-password')
        ->set('editUserIsAdmin', true)
        ->call('updateUser')
        ->assertHasNoErrors();

    $target->refresh();

    expect($target->name)->toBe('Updated User')
        ->and($target->email)->toBe('updated@example.com')
        ->and($target->is_admin)->toBeTrue();

    expect(Hash::check('new-password', $target->password))->toBeTrue();
});

it('keeps existing password when admin updates user without a new password', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $target = User::factory()->create([
        'password' => 'password',
    ]);

    $originalPassword = $target->password;

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->call('openEditUserModal', $target->id)
        ->set('editUserName', 'No Password Change')
        ->set('editUserEmail', 'no-password-change@example.com')
        ->set('editUserPassword', '')
        ->set('editUserPasswordConfirmation', '')
        ->set('editUserIsAdmin', false)
        ->call('updateUser')
        ->assertHasNoErrors();

    $target->refresh();

    expect($target->password)->toBe($originalPassword);
});

it('prevents admin users from deleting their own account', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->call('openDeleteUserModal', $admin->id)
        ->call('deleteUser')
        ->assertForbidden();
});

it('prevents deleting the last admin account', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $otherAdmin = User::factory()->create([
        'is_admin' => true,
    ]);

    $otherAdmin->update(['is_admin' => false]);

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->call('openDeleteUserModal', $admin->id)
        ->call('deleteUser')
        ->assertForbidden();
});

it('allows admin users to delete non-admin users', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $target = User::factory()->create([
        'is_admin' => false,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::users.index')
        ->call('openDeleteUserModal', $target->id)
        ->call('deleteUser')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('users', [
        'id' => $target->id,
    ]);
});
