<?php

use App\Models\AiConnection;
use App\Models\User;

test('owner is allowed to view, update, delete, and makeDefault their own ai connection', function () {
    $owner = User::factory()->create();
    $connection = AiConnection::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('viewAny', AiConnection::class))->toBeTrue()
        ->and($owner->can('create', AiConnection::class))->toBeTrue()
        ->and($owner->can('view', $connection))->toBeTrue()
        ->and($owner->can('update', $connection))->toBeTrue()
        ->and($owner->can('delete', $connection))->toBeTrue()
        ->and($owner->can('makeDefault', $connection))->toBeTrue();
});

test('non-owner is denied for view, update, delete, and makeDefault on another users ai connection', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $connection = AiConnection::factory()->create(['user_id' => $owner->id]);

    expect($otherUser->can('view', $connection))->toBeFalse()
        ->and($otherUser->can('update', $connection))->toBeFalse()
        ->and($otherUser->can('delete', $connection))->toBeFalse()
        ->and($otherUser->can('makeDefault', $connection))->toBeFalse();
});
