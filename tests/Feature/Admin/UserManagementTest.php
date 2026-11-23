<?php

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('only an administrator can open the account list', function (string $state, int $status) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/admin/users')->assertStatus($status);
})->with([
    'administrator' => ['admin', 200],
    'technician' => ['technician', 403],
]);

test('a regular user cannot manage accounts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->get('/admin/users')->assertForbidden();
    $this->actingAs($user)->get('/admin/users/create')->assertForbidden();
    $this->actingAs($user)->post('/admin/users', [])->assertForbidden();
    $this->actingAs($user)->get("/admin/users/{$other->id}/edit")->assertForbidden();
    $this->actingAs($user)->put("/admin/users/{$other->id}", [])->assertForbidden();
});

test('an administrator can create an account', function () {
    $admin = User::factory()->admin()->create();
    $department = Department::factory()->create();

    $this->actingAs($admin)->get('/admin/users/create')->assertOk();

    $this->actingAs($admin)
        ->post('/admin/users', [
            'name' => 'Camille Durand',
            'email' => 'camille.durand@example.com',
            'role' => UserRole::Technician->value,
            'department_id' => $department->id,
            'password' => 'un-mot-de-passe-long',
        ])
        ->assertRedirect('/admin/users');

    $created = User::query()->where('email', 'camille.durand@example.com')->firstOrFail();

    expect($created->role)->toBe(UserRole::Technician)
        ->and($created->department_id)->toBe($department->id)
        ->and($created->is_active)->toBeTrue()
        ->and(Hash::check('un-mot-de-passe-long', $created->password))->toBeTrue();
});

test('account creation is validated', function () {
    $admin = User::factory()->admin()->create();
    $existing = User::factory()->create();

    $this->actingAs($admin)
        ->post('/admin/users', [
            'name' => '',
            'email' => $existing->email,
            'role' => 'superuser',
            'password' => 'court',
        ])
        ->assertSessionHasErrors(['name', 'email', 'role', 'password']);
});

test('an administrator can update an account without changing its password', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)->get("/admin/users/{$user->id}/edit")->assertOk();

    $this->actingAs($admin)
        ->put("/admin/users/{$user->id}", [
            'name' => 'Nouveau Nom',
            'email' => $user->email,
            'role' => UserRole::Technician->value,
            'is_active' => '1',
            'password' => '',
        ])
        ->assertRedirect('/admin/users');

    $user->refresh();

    expect($user->name)->toBe('Nouveau Nom')
        ->and($user->role)->toBe(UserRole::Technician)
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

test('an administrator can deactivate an account', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->put("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => '0',
        ])
        ->assertRedirect('/admin/users');

    expect($user->refresh()->is_active)->toBeFalse();
});

test('an administrator cannot deactivate or demote their own account', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put("/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::User->value,
            'is_active' => '0',
        ])
        ->assertSessionHasErrors(['role', 'is_active']);

    $admin->refresh();

    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->is_active)->toBeTrue();
});
