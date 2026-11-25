<?php

use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\User;

test('project owner is allowed to viewAny, view, create, update, and delete repository connections', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->create(['project_id' => $project->id]);

    expect($owner->can('viewAny', [RepositoryConnection::class, $project]))->toBeTrue()
        ->and($owner->can('create', [RepositoryConnection::class, $project]))->toBeTrue()
        ->and($owner->can('view', $connection))->toBeTrue()
        ->and($owner->can('update', $connection))->toBeTrue()
        ->and($owner->can('delete', $connection))->toBeTrue();
});

test('non-owner is denied for every repository connection policy ability', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->create(['project_id' => $project->id]);

    expect($otherUser->can('viewAny', [RepositoryConnection::class, $project]))->toBeFalse()
        ->and($otherUser->can('create', [RepositoryConnection::class, $project]))->toBeFalse()
        ->and($otherUser->can('view', $connection))->toBeFalse()
        ->and($otherUser->can('update', $connection))->toBeFalse()
        ->and($otherUser->can('delete', $connection))->toBeFalse();
});
