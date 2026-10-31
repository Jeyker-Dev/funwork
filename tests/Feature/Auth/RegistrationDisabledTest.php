<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('public registration is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

test('registration screen is not reachable', function () {
    $this->get('/register')->assertNotFound();
});

test('registration endpoint does not create users', function () {
    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

test('login page does not link to registration', function () {
    $this->get(route('login'))->assertOk()->assertDontSee('Sign up');
});
