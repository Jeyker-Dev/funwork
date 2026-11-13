<?php

use App\Actions\Decisions\UpdateDecisionAction;
use App\Data\Decisions\DecisionData;
use App\Enums\DecisionStatus;
use App\Exceptions\Decisions\InvalidSupersedingDecisionException;
use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('updates decision content, status, and superseding decision', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->proposed()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
    ]);
    $replacement = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 2,
    ]);
    $action = app(UpdateDecisionAction::class);

    $action($decision, $owner, new DecisionData(
        title: 'Updated title',
        status: DecisionStatus::Superseded,
        context: 'Updated context',
        decision: 'Updated decision',
        consequences: 'Updated consequences',
        decidedAt: Carbon::parse('2026-11-13'),
        supersededById: $replacement->id,
    ));

    $fresh = $decision->fresh();

    expect($fresh->title)->toBe('Updated title')
        ->and($fresh->status)->toBe(DecisionStatus::Superseded)
        ->and($fresh->context)->toBe('Updated context')
        ->and($fresh->decision)->toBe('Updated decision')
        ->and($fresh->consequences)->toBe('Updated consequences')
        ->and($fresh->superseded_by_id)->toBe($replacement->id)
        ->and($fresh->decided_at?->toDateString())->toBe('2026-11-13');
});

it('clears superseded_by_id when status changes away from Superseded', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $replacement = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 2,
    ]);
    $decision = Decision::factory()->superseded($replacement)->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
    ]);
    $action = app(UpdateDecisionAction::class);

    $action($decision, $owner, new DecisionData(
        title: $decision->title,
        status: DecisionStatus::Accepted,
        context: $decision->context,
        decision: $decision->decision,
        consequences: $decision->consequences,
        supersededById: $replacement->id,
    ));

    $fresh = $decision->fresh();

    expect($fresh->status)->toBe(DecisionStatus::Accepted)
        ->and($fresh->superseded_by_id)->toBeNull();
});

it('rejects updating to Superseded without a superseding decision', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->proposed()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);
    $action = app(UpdateDecisionAction::class);

    expect(fn () => $action($decision, $owner, new DecisionData(
        title: 'Should not persist',
        status: DecisionStatus::Superseded,
        context: $decision->context,
        decision: $decision->decision,
        consequences: $decision->consequences,
        supersededById: null,
    )))->toThrow(InvalidSupersedingDecisionException::class);

    expect($decision->fresh()->status)->toBe(DecisionStatus::Proposed)
        ->and($decision->fresh()->superseded_by_id)->toBeNull();
});

it('rejects a decision superseding itself on update', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->proposed()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);
    $action = app(UpdateDecisionAction::class);

    expect(fn () => $action($decision, $owner, new DecisionData(
        title: $decision->title,
        status: DecisionStatus::Superseded,
        context: $decision->context,
        decision: $decision->decision,
        consequences: $decision->consequences,
        supersededById: $decision->id,
    )))->toThrow(InvalidSupersedingDecisionException::class);

    expect($decision->fresh()->status)->toBe(DecisionStatus::Proposed)
        ->and($decision->fresh()->superseded_by_id)->toBeNull();
});

it('rejects a superseding decision from another project on update', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $otherProject = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->proposed()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);
    $foreignDecision = Decision::factory()->accepted()->create([
        'project_id' => $otherProject->id,
        'created_by' => $owner->id,
    ]);
    $action = app(UpdateDecisionAction::class);

    expect(fn () => $action($decision, $owner, new DecisionData(
        title: $decision->title,
        status: DecisionStatus::Superseded,
        context: $decision->context,
        decision: $decision->decision,
        consequences: $decision->consequences,
        supersededById: $foreignDecision->id,
    )))->toThrow(InvalidSupersedingDecisionException::class);

    expect($decision->fresh()->status)->toBe(DecisionStatus::Proposed)
        ->and($decision->fresh()->superseded_by_id)->toBeNull();
});

it('forbids non-owner from updating a decision', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->proposed()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'title' => 'Original title',
    ]);
    $action = app(UpdateDecisionAction::class);

    expect(fn () => $action($decision, $otherUser, new DecisionData(
        title: 'Hacked',
        status: DecisionStatus::Accepted,
        context: 'Context',
        decision: 'Decision',
        consequences: 'Consequences',
    )))->toThrow(AuthorizationException::class);

    expect($decision->fresh()->title)->toBe('Original title')
        ->and($decision->fresh()->status)->toBe(DecisionStatus::Proposed);
});
