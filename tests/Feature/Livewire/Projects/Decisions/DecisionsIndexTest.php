<?php

use App\Enums\DecisionStatus;
use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('displays project decisions with number, title, status, date, and detail link', function () {
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
        ->assertSee('Why the project is built the way it is.')
        ->assertSee('#1')
        ->assertSee('Adopt Livewire 4 SFCs')
        ->assertSee('Accepted')
        ->assertSee('Nov 13, 2026')
        ->assertSee(route('project.decisions.show', [$project, $decision]));
});

it('forbids another user from viewing a project decisions list', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($otherUser)
        ->get(route('project.decisions', $project))
        ->assertForbidden();
});

it('shows status filter chips with counts on the same line and hides zero-count statuses', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'title' => 'Accepted ADR One',
    ]);
    Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'title' => 'Accepted ADR Two',
    ]);
    Decision::factory()->proposed()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'title' => 'Proposed ADR One',
    ]);

    $component = Livewire::actingAs($owner)
        ->test('pages::projects.decisions.index', ['project' => $project])
        ->assertSeeInOrder(['All', '3'])
        ->assertSeeInOrder(['Proposed', '1'])
        ->assertSeeInOrder(['Accepted', '2']);

    $html = $component->html();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $filterNav = $xpath->query('//nav[@data-status-filters]')->item(0);

    expect($filterNav)->not->toBeNull()
        ->and($filterNav->textContent)->not->toContain('Rejected')
        ->and($filterNav->textContent)->not->toContain('Superseded')
        ->and($html)->toContain('data-status-chip="all"')
        ->and($html)->toContain('data-status-chip="Proposed"')
        ->and($html)->toContain('data-status-chip="Accepted"')
        ->and($html)->not->toContain('data-status-chip="Rejected"')
        ->and($html)->not->toContain('data-status-chip="Superseded"');

    $component->call('selectStatus', DecisionStatus::Accepted->value)
        ->assertSet('statusFilter', DecisionStatus::Accepted->value)
        ->assertSee('Accepted ADR One')
        ->assertDontSee('Proposed ADR One');
});

it('filters decisions by search query across title and content', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
        'title' => 'Adopt PostgreSQL',
        'context' => 'Relational data needs',
        'decision' => 'Use PostgreSQL 17',
        'consequences' => 'Strong consistency',
    ]);

    Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 2,
        'title' => 'Cache with Redis',
        'context' => 'Session lookup latency',
        'decision' => 'Introduce Redis cluster',
        'consequences' => 'Extra operational service',
    ]);

    Livewire::actingAs($owner)
        ->test('pages::projects.decisions.index', ['project' => $project])
        ->set('search', 'PostgreSQL')
        ->assertSee('Adopt PostgreSQL')
        ->assertDontSee('Cache with Redis')
        ->set('search', 'latency')
        ->assertSee('Cache with Redis')
        ->assertDontSee('Adopt PostgreSQL');
});

it('renders superseded rows muted with only a Superseded by #N link and no duplicate status label', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $replacement = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 2,
        'title' => 'Use Livewire SFCs',
    ]);

    $superseded = Decision::factory()->superseded($replacement)->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
        'title' => 'Use Volt components',
    ]);

    $html = Livewire::actingAs($owner)
        ->test('pages::projects.decisions.index', ['project' => $project])
        ->assertSee('Superseded by #2')
        ->assertSee(route('project.decisions.show', [$project, $replacement]))
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $row = $xpath->query(sprintf('//*[@data-decision-row="%d"]', $superseded->id))->item(0);
    expect($row)->not->toBeNull();

    $rowText = preg_replace('/\s+/', ' ', trim($row->textContent));
    expect(substr_count($rowText, 'Superseded'))->toBe(1)
        ->and($rowText)->toContain('Superseded by #2');
});

it('shows the empty state with log_decision hint when project has no decisions', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('project.decisions', $project))
        ->assertSuccessful()
        ->assertSee('Record why the project is built this way')
        ->assertSee('New decision')
        ->assertSee('or ask your agent to log one with')
        ->assertSee('log_decision')
        ->assertDontSee('Logged by MCP agent');
});
