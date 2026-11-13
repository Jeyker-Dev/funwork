<?php

use App\Enums\DecisionStatus;
use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('allows project owner to create decisions with sequential numbers per project', function () {
    $owner = User::factory()->create();
    $projectA = Project::factory()->create(['owner_id' => $owner->id]);
    $projectB = Project::factory()->create(['owner_id' => $owner->id]);

    Livewire::actingAs($owner)
        ->test('pages::projects.decisions.index', ['project' => $projectA])
        ->set('form.title', 'First decision in A')
        ->set('form.status', DecisionStatus::Proposed->value)
        ->set('form.context', 'Context A1')
        ->set('form.decision', 'Decision A1')
        ->set('form.consequences', 'Consequences A1')
        ->call('createDecision')
        ->assertHasNoErrors();

    Livewire::actingAs($owner)
        ->test('pages::projects.decisions.index', ['project' => $projectA])
        ->set('form.title', 'Second decision in A')
        ->set('form.status', DecisionStatus::Accepted->value)
        ->set('form.decidedAt', '2026-11-13')
        ->set('form.context', 'Context A2')
        ->set('form.decision', 'Decision A2')
        ->set('form.consequences', 'Consequences A2')
        ->call('createDecision')
        ->assertHasNoErrors();

    Livewire::actingAs($owner)
        ->test('pages::projects.decisions.index', ['project' => $projectB])
        ->set('form.title', 'First decision in B')
        ->set('form.status', DecisionStatus::Proposed->value)
        ->set('form.context', 'Context B1')
        ->set('form.decision', 'Decision B1')
        ->set('form.consequences', 'Consequences B1')
        ->call('createDecision')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('decisions', [
        'project_id' => $projectA->id,
        'number' => 1,
        'title' => 'First decision in A',
    ]);

    $this->assertDatabaseHas('decisions', [
        'project_id' => $projectA->id,
        'number' => 2,
        'title' => 'Second decision in A',
        'status' => DecisionStatus::Accepted->value,
    ]);

    $this->assertDatabaseHas('decisions', [
        'project_id' => $projectB->id,
        'number' => 1,
        'title' => 'First decision in B',
    ]);
});

it('validates required fields when creating a decision', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    Livewire::actingAs($owner)
        ->test('pages::projects.decisions.index', ['project' => $project])
        ->set('form.title', '')
        ->set('form.context', '')
        ->set('form.decision', '')
        ->set('form.consequences', '')
        ->call('createDecision')
        ->assertHasErrors([
            'form.title' => 'required',
            'form.context' => 'required',
            'form.decision' => 'required',
            'form.consequences' => 'required',
        ]);

    $this->assertDatabaseCount('decisions', 0);
});

it('allows project owner to edit decision content and transition to Superseded with same-project decision', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
        'title' => 'Original Title',
    ]);
    $replacement = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 2,
        'title' => 'Newer Approach',
    ]);

    Livewire::actingAs($owner)
        ->test('pages::projects.decisions.show', ['project' => $project, 'decision' => $decision])
        ->set('form.title', 'Updated Title')
        ->set('form.status', DecisionStatus::Superseded->value)
        ->set('form.supersededById', $replacement->id)
        ->set('form.decidedAt', '2026-11-13')
        ->set('form.context', 'Updated context')
        ->set('form.decision', 'Updated decision')
        ->set('form.consequences', 'Updated consequences')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Updated Title')
        ->assertSee('ADR-002: Newer Approach');

    $fresh = $decision->fresh();

    expect($fresh->title)->toBe('Updated Title')
        ->and($fresh->status)->toBe(DecisionStatus::Superseded)
        ->and($fresh->superseded_by_id)->toBe($replacement->id);
});

it('requires a superseding decision when status is Superseded on edit', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test('pages::projects.decisions.show', ['project' => $project, 'decision' => $decision])
        ->set('form.status', DecisionStatus::Superseded->value)
        ->set('form.supersededById', null)
        ->call('save')
        ->assertHasErrors(['form.supersededById']);

    expect($decision->fresh()->status)->toBe(DecisionStatus::Accepted);
});

it('rejects a superseding decision from another project on edit', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $otherProject = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);
    $foreignDecision = Decision::factory()->accepted()->create([
        'project_id' => $otherProject->id,
        'created_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test('pages::projects.decisions.show', ['project' => $project, 'decision' => $decision])
        ->set('form.status', DecisionStatus::Superseded->value)
        ->set('form.supersededById', $foreignDecision->id)
        ->call('save')
        ->assertHasErrors(['form.supersededById']);

    expect($decision->fresh()->status)->toBe(DecisionStatus::Accepted);
});

it('forbids another user from creating or editing someone elses project decision', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->proposed()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'title' => 'Protected Decision',
    ]);

    Livewire::actingAs($otherUser)
        ->test('pages::projects.decisions.index', ['project' => $project])
        ->assertForbidden();

    Livewire::actingAs($otherUser)
        ->test('pages::projects.decisions.show', ['project' => $project, 'decision' => $decision])
        ->assertForbidden();

    expect($decision->fresh()->title)->toBe('Protected Decision');
});
