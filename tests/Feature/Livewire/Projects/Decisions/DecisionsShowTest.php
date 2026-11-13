<?php

use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders context, decision, and consequences as safe markdown and strips XSS', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
        'title' => 'Safe Markdown ADR',
        'context' => "## Background\n\n<script>alert('xss')</script>",
        'decision' => 'Use **CommonMark** with [bad](javascript:alert(1)).',
        'consequences' => '<div onclick="alert(1)">Safe text</div>',
        'decided_at' => '2026-11-13',
    ]);

    $response = $this->actingAs($owner)
        ->get(route('project.decisions.show', [$project, $decision]))
        ->assertSuccessful()
        ->assertSee('ADR-001')
        ->assertSee('Safe Markdown ADR')
        ->assertSee('<h2>Background</h2>', false)
        ->assertSee('<strong>CommonMark</strong>', false);

    $content = $response->getContent();

    expect($content)->not->toContain("<script>alert('xss')</script>")
        ->and($content)->not->toContain('href="javascript:alert(1)"')
        ->and($content)->not->toContain('<div onclick="alert(1)">');
});

it('displays link to superseding decision when superseded', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $replacement = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 2,
        'title' => 'Replacement ADR',
    ]);
    $original = Decision::factory()->superseded($replacement)->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
        'title' => 'Original ADR',
    ]);

    $this->actingAs($owner)
        ->get(route('project.decisions.show', [$project, $original]))
        ->assertSuccessful()
        ->assertSee('ADR-002: Replacement ADR')
        ->assertSee(route('project.decisions.show', [$project, $replacement]));
});

it('forbids another user from viewing a decision', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($otherUser)
        ->get(route('project.decisions.show', [$project, $decision]))
        ->assertForbidden();
});

it('returns 404 when accessing a decision through another project URL', function () {
    $owner = User::factory()->create();
    $projectA = Project::factory()->create(['owner_id' => $owner->id]);
    $projectB = Project::factory()->create(['owner_id' => $owner->id]);
    $decision = Decision::factory()->create([
        'project_id' => $projectB->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('project.decisions.show', [$projectA, $decision]))
        ->assertNotFound();
});
