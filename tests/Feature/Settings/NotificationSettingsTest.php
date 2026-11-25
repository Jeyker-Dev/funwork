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
        ->assertSee('Morning Digest')
        ->assertSee('Email me the daily digest');

    Livewire::actingAs($user)
        ->test('pages::settings.notifications')
        ->assertSet('digestEnabled', true)
        ->assertSet('digestEmailEnabled', false)
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

test('user can toggle digest email enabled immediately', function () {
    $user = User::factory()->create([
        'digest_enabled' => true,
        'digest_email_enabled' => false,
        'digest_hour' => 8,
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.notifications')
        ->set('digestEmailEnabled', true)
        ->assertHasNoErrors()
        ->assertDispatched('notifications-updated');

    expect($user->fresh()->digest_email_enabled)->toBeTrue();

    Livewire::actingAs($user->fresh())
        ->test('pages::settings.notifications')
        ->set('digestEmailEnabled', false)
        ->assertHasNoErrors();

    expect($user->fresh()->digest_email_enabled)->toBeFalse();
});

test('digest email switch is disabled while morning digest is off', function () {
    $user = User::factory()->create([
        'digest_enabled' => false,
        'digest_email_enabled' => false,
    ]);

    $html = Livewire::actingAs($user)
        ->test('pages::settings.notifications')
        ->html();

    expect($html)->toMatch('/<ui-switch[^>]*wire:model\.live="digestEmailEnabled"[^>]*disabled/');
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
