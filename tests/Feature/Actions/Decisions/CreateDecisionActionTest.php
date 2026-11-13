<?php

use App\Actions\Decisions\CreateDecisionAction;
use App\Data\Decisions\DecisionData;
use App\Enums\DecisionStatus;
use App\Exceptions\Decisions\InvalidSupersedingDecisionException;
use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates decisions with sequential numbers starting at 1 independently per project', function () {
    $owner = User::factory()->create();
    $projectA = Project::factory()->create(['owner_id' => $owner->id]);
    $projectB = Project::factory()->create(['owner_id' => $owner->id]);
    $action = app(CreateDecisionAction::class);

    $input = new DecisionData(
        title: 'Use Pest for testing',
        status: DecisionStatus::Accepted,
        context: 'We need a test runner.',
        decision: 'Use Pest v4.',
        consequences: 'Expressive test syntax.',
    );

    $a1 = $action($projectA, $owner, $input);
    $a2 = $action($projectA, $owner, $input);
    $b1 = $action($projectB, $owner, $input);

    expect($a1->number)->toBe(1)
        ->and($a2->number)->toBe(2)
        ->and($b1->number)->toBe(1)
        ->and($a1->created_by)->toBe($owner->id);

    $this->assertDatabaseHas('decisions', [
        'id' => $a1->id,
        'project_id' => $projectA->id,
        'number' => 1,
        'title' => 'Use Pest for testing',
        'status' => DecisionStatus::Accepted->value,
        'context' => 'We need a test runner.',
        'decision' => 'Use Pest v4.',
        'consequences' => 'Expressive test syntax.',
        'created_by' => $owner->id,
    ]);
});

it('forbids non-owner from creating a decision', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $action = app(CreateDecisionAction::class);

    $input = new DecisionData(
        title: 'Unauthorized decision',
        status: DecisionStatus::Proposed,
        context: 'Context',
        decision: 'Decision',
        consequences: 'Consequences',
    );

    expect(fn () => $action($project, $otherUser, $input))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('decisions', 0);
});

it('rejects creating a superseded decision without a superseding decision', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $action = app(CreateDecisionAction::class);

    expect(fn () => $action($project, $owner, new DecisionData(
        title: 'Missing superseding',
        status: DecisionStatus::Superseded,
        context: 'Context',
        decision: 'Decision',
        consequences: 'Consequences',
        supersededById: null,
    )))->toThrow(InvalidSupersedingDecisionException::class);

    $this->assertDatabaseCount('decisions', 0);
});

it('rejects creating a superseded decision pointing to a decision from another project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $otherProject = Project::factory()->create(['owner_id' => $owner->id]);
    $otherDecision = Decision::factory()->create(['project_id' => $otherProject->id, 'created_by' => $owner->id]);
    $action = app(CreateDecisionAction::class);

    expect(fn () => $action($project, $owner, new DecisionData(
        title: 'Cross-project superseding',
        status: DecisionStatus::Superseded,
        context: 'Context',
        decision: 'Decision',
        consequences: 'Consequences',
        supersededById: $otherDecision->id,
    )))->toThrow(InvalidSupersedingDecisionException::class);

    $this->assertDatabaseCount('decisions', 1);
});
