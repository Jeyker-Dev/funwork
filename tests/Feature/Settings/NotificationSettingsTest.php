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
        ->assertHasNoErrors()
        ->assertDispatched('notifications-updated');

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

test('browser notifications section is hidden when VAPID public key is missing', function () {
    config(['webpush.vapid.public_key' => null]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('notifications.edit'))
        ->assertSuccessful()
        ->assertDontSee('Browser notifications')
        ->assertDontSee('Enable push on this browser');
});

test('browser notifications section is visible with iOS note when VAPID public key is configured', function () {
    config(['webpush.vapid.public_key' => 'test-vapid-public-key']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('notifications.edit'))
        ->assertSuccessful()
        ->assertSee('Browser notifications')
        ->assertSee('Enable push on this browser')
        ->assertSee('installed to the Home Screen')
        ->assertSee('test-vapid-public-key');
});
