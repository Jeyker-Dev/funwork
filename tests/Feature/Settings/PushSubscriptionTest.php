<?php

use App\Models\User;
use NotificationChannels\WebPush\PushSubscription;

it('returns 401 when an unauthenticated user posts a push subscription', function () {
    $this->postJson(route('notifications.push-subscription.store'), [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/sample',
        'keys' => [
            'p256dh' => 'public-key',
            'auth' => 'auth-token',
        ],
    ])->assertUnauthorized();
});

it('returns 401 when an unauthenticated user deletes a push subscription', function () {
    $this->deleteJson(route('notifications.push-subscription.destroy'), [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/sample',
    ])->assertUnauthorized();
});

it('stores a valid push subscription for the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('notifications.push-subscription.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => [
                'p256dh' => 'p256dh-key-value',
                'auth' => 'auth-secret-value',
            ],
            'contentEncoding' => 'aes128gcm',
        ])
        ->assertCreated();

    $subscription = PushSubscription::where('endpoint', 'https://fcm.googleapis.com/fcm/send/abc123')->first();

    expect($subscription)->not->toBeNull()
        ->and($user->ownsPushSubscription($subscription))->toBeTrue()
        ->and($subscription->public_key)->toBe('p256dh-key-value')
        ->and($subscription->auth_token)->toBe('auth-secret-value');
});

it('updates an existing push subscription when the same user re-subscribes with the same endpoint', function () {
    $user = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/same-device';

    $user->updatePushSubscription($endpoint, 'old-key', 'old-auth');

    $this->actingAs($user)
        ->postJson(route('notifications.push-subscription.store'), [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'new-key',
                'auth' => 'new-auth',
            ],
        ])
        ->assertCreated();

    expect($user->pushSubscriptions()->count())->toBe(1)
        ->and($user->pushSubscriptions()->first()->public_key)->toBe('new-key');
});

it('rejects invalid push subscription payloads', function (array $payload, string $errorField) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('notifications.push-subscription.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$errorField]);
})->with([
    'missing endpoint' => [
        ['keys' => ['p256dh' => 'key', 'auth' => 'token']],
        'endpoint',
    ],
    'invalid endpoint url' => [
        ['endpoint' => 'not-a-url', 'keys' => ['p256dh' => 'key', 'auth' => 'token']],
        'endpoint',
    ],
    'missing p256dh key' => [
        ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['auth' => 'token']],
        'keys.p256dh',
    ],
    'missing auth key' => [
        ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'key']],
        'keys.auth',
    ],
]);

it('deletes an existing push subscription on unsubscribe', function () {
    $user = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/unsubscribe-me';

    $user->updatePushSubscription($endpoint, 'key', 'auth');

    $this->actingAs($user)
        ->deleteJson(route('notifications.push-subscription.destroy'), [
            'endpoint' => $endpoint,
        ])
        ->assertSuccessful();

    expect($user->pushSubscriptions()->count())->toBe(0);
});

it('prevents deleting another users push subscription', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherEndpoint = 'https://fcm.googleapis.com/fcm/send/other-device';

    $otherUser->updatePushSubscription($otherEndpoint, 'key', 'auth');

    $this->actingAs($user)
        ->deleteJson(route('notifications.push-subscription.destroy'), [
            'endpoint' => $otherEndpoint,
        ])
        ->assertSuccessful();

    expect($otherUser->pushSubscriptions()->count())->toBe(1);
});
