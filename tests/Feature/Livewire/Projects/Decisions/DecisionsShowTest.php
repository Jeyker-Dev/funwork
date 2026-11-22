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
        ->assertSee('#1')
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

    $response = $this->actingAs($owner)
        ->get(route('project.decisions.show', [$project, $original]))
        ->assertSuccessful()
        ->assertSeeInOrder(['This decision was superseded by', '#2 Replacement ADR'])
        ->assertSee('Superseded by #2 Replacement ADR')
        ->assertSee(route('project.decisions.show', [$project, $replacement]));

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $notice = $xpath->query('//*[@data-superseded-notice]')->item(0);

    expect($notice)->not->toBeNull()
        ->and(preg_replace('/\s+/', ' ', trim($notice->textContent)))->toContain('This decision was superseded by #2 Replacement ADR');
});

it('shows the superseded notice only on superseded decisions', function () {
    $owner = User::factory()->create(['name' => 'Alex Rivera']);
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $accepted = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 2,
        'title' => 'Active Decision',
    ]);
    $superseded = Decision::factory()->superseded($accepted)->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
        'title' => 'Old Decision',
    ]);

    $this->actingAs($owner)
        ->get(route('project.decisions.show', [$project, $accepted]))
        ->assertSuccessful()
        ->assertDontSee('data-superseded-notice', false)
        ->assertDontSee('This decision was superseded by')
        ->assertSee('Alex Rivera')
        ->assertDontSee('Logged by MCP agent');

    $this->actingAs($owner)
        ->get(route('project.decisions.show', [$project, $superseded]))
        ->assertSuccessful()
        ->assertSee('data-superseded-notice', false)
        ->assertSeeInOrder(['This decision was superseded by', '#2 Active Decision']);
});

it('displays the supersede chain in both directions on the detail panel', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $newest = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 3,
        'title' => 'Newest Decision',
    ]);
    $middle = Decision::factory()->superseded($newest)->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 2,
        'title' => 'Middle Decision',
    ]);
    $oldest = Decision::factory()->superseded($middle)->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
        'title' => 'Oldest Decision',
    ]);

    $this->actingAs($owner)
        ->get(route('project.decisions.show', [$project, $middle]))
        ->assertSuccessful()
        ->assertSee('Supersedes #1 Oldest Decision')
        ->assertSee(route('project.decisions.show', [$project, $oldest]))
        ->assertSee('Superseded by #3 Newest Decision')
        ->assertSee(route('project.decisions.show', [$project, $newest]));
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
