<?php

use App\Models\AiConversation;
use App\Models\Project;
use App\Models\User;

test('owner with project access can view, update, delete, and send; another user is denied', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $conversation = AiConversation::factory()->create([
        'user_id' => $owner->id,
        'project_id' => $project->id,
    ]);

    expect($owner->can('viewAny', [AiConversation::class, $project]))->toBeTrue()
        ->and($owner->can('create', [AiConversation::class, $project]))->toBeTrue()
        ->and($owner->can('view', $conversation))->toBeTrue()
        ->and($owner->can('update', $conversation))->toBeTrue()
        ->and($owner->can('delete', $conversation))->toBeTrue()
        ->and($owner->can('send', $conversation))->toBeTrue();

    expect($otherUser->can('viewAny', [AiConversation::class, $project]))->toBeFalse()
        ->and($otherUser->can('create', [AiConversation::class, $project]))->toBeFalse()
        ->and($otherUser->can('view', $conversation))->toBeFalse()
        ->and($otherUser->can('update', $conversation))->toBeFalse()
        ->and($otherUser->can('delete', $conversation))->toBeFalse()
        ->and($otherUser->can('send', $conversation))->toBeFalse();
});

test('conversation owner loses access after losing view access to the project', function () {
    $owner = User::factory()->create();
    $newOwner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $conversation = AiConversation::factory()->create([
        'user_id' => $owner->id,
        'project_id' => $project->id,
    ]);

    // Simulate user losing access to the project.
    $project->update(['owner_id' => $newOwner->id]);
    $conversation->refresh();

    expect($owner->can('view', $conversation))->toBeFalse()
        ->and($owner->can('update', $conversation))->toBeFalse()
        ->and($owner->can('delete', $conversation))->toBeFalse()
        ->and($owner->can('send', $conversation))->toBeFalse()
        ->and($newOwner->can('view', $conversation))->toBeFalse();
});
