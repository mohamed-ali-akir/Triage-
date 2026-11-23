<?php

use App\Models\User;

test('the login page is displayed', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Connexion');
});

test('a guest is redirected to the login page', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/profile')->assertRedirect('/login');
});

test('a user can log in with valid credentials', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

test('a user cannot log in with a wrong password', function () {
    $user = User::factory()->create();

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login attempts are rate limited', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertTooManyRequests();
});

test('a user can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});

test('there is no public registration', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Intrus',
        'email' => 'intrus@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertDatabaseCount('users', 0);
});
