<?php

use App\Models\User;

test('a deactivated account cannot log in', function () {
    $user = User::factory()->inactive()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a user deactivated while logged in is logged out on the next request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')->assertOk();

    $user->update(['is_active' => false]);

    $this->get('/profile')->assertRedirect('/login');
    $this->assertGuest();
});

test('support staff land on the ticket queue', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/')
        ->assertRedirect('/queue');
})->with(['technician', 'admin']);
