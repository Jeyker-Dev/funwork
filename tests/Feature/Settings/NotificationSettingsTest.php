<?php

use App\Models\User;
use Livewire\Livewire;

test('notification settings page redirects guests to login', function () {
    $this->get(route('notifications.edit'))
        ->assertRedirect(route('login'));
});

test('notification settings page displays defaults for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('notifications.edit'))
        ->assertSuccessful()
        ->assertSee('Morning Digest');

    Livewire::actingAs($user)
        ->test('pages::settings.notifications')
        ->assertSet('digestEnabled', true)
        ->assertSet('digestHour', 8);
});

test('user can update preferred delivery hour', function () {
    $user = User::factory()->create([
        'digest_enabled' => true,
        'digest_hour' => 8,
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.notifications')
        ->set('digestHour', 14)
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->digest_enabled)->toBeTrue()
        ->and($user->digest_hour)->toBe(14);
});

test('user can toggle digest enabled', function () {
    $user = User::factory()->create([
        'digest_enabled' => true,
        'digest_hour' => 8,
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.notifications')
        ->set('digestEnabled', false)
        ->assertHasNoErrors();

    expect($user->fresh()->digest_enabled)->toBeFalse();
});

test('digest hour must be between 0 and 23', function (int $invalidHour) {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.notifications')
        ->set('digestHour', $invalidHour)
        ->call('save')
        ->assertHasErrors(['digestHour']);
})->with([
    'negative' => -1,
    'too high' => 24,
]);
