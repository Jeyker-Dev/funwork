<?php

use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('project owner can viewAny, create, view, and update decisions', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);

    expect($owner->can('viewAny', [Decision::class, $project]))->toBeTrue()
        ->and($owner->can('create', [Decision::class, $project]))->toBeTrue()
        ->and($owner->can('view', $decision))->toBeTrue()
        ->and($owner->can('update', $decision))->toBeTrue()
        ->and($owner->can('delete', $decision))->toBeFalse();
});

test('non-owner cannot viewAny, create, view, or update another users project decisions', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);

    expect($otherUser->can('viewAny', [Decision::class, $project]))->toBeFalse()
        ->and($otherUser->can('create', [Decision::class, $project]))->toBeFalse()
        ->and($otherUser->can('view', $decision))->toBeFalse()
        ->and($otherUser->can('update', $decision))->toBeFalse();
});
