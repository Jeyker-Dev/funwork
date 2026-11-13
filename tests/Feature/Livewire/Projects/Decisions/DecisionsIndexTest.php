<?php

use App\Enums\DecisionStatus;
use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('displays project decisions with ADR number, title, status badge, date, and sidebar link', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
        'title' => 'Adopt Livewire 4 SFCs',
        'decided_at' => '2026-11-13',
    ]);

    $this->actingAs($owner)
        ->get(route('project.decisions', $project))
        ->assertSuccessful()
        ->assertSee('Decisions')
        ->assertSee('ADR-001')
        ->assertSee('Adopt Livewire 4 SFCs')
        ->assertSee('Accepted')
        ->assertSee('Nov 13, 2026')
        ->assertSee(route('project.decisions.show', [$project, $decision]));
});

it('filters decisions by status', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    Decision::factory()->proposed()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
        'title' => 'Proposed ADR',
    ]);

    Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 2,
        'title' => 'Accepted ADR',
    ]);

    Livewire::actingAs($owner)
        ->test('pages::projects.decisions.index', ['project' => $project])
        ->assertSee('Proposed ADR')
        ->assertSee('Accepted ADR')
        ->set('statusFilter', DecisionStatus::Accepted->value)
        ->assertDontSee('Proposed ADR')
        ->assertSee('Accepted ADR');
});

it('forbids another user from viewing a project decisions list', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($otherUser)
        ->get(route('project.decisions', $project))
        ->assertForbidden();
});
