<?php

use App\Actions\Auth\CreateApiTokenAction;
use App\Enums\DecisionStatus;
use App\Mcp\Servers\FunWorkServer;
use App\Mcp\Tools\ListDecisionsTool;
use App\Mcp\Tools\LogDecisionTool;
use App\Models\Decision;
use App\Models\Project;
use App\Models\User;

test('FunWorkServer registers list_decisions and log_decision', function () {
    $user = User::factory()->create();
    $created = app(CreateApiTokenAction::class)($user, 'ADR Client');

    $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'list_decisions'])
        ->assertJsonFragment(['name' => 'log_decision']);
});

test('log_decision creates decisions with sequential ADR numbers and returns decisionDetail', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $firstPayload = [
        'project_id' => $project->id,
        'title' => 'Use PostgreSQL for analytics',
        'context' => "## Context\n\nWe need JSONB queries.",
        'decision' => 'Adopt PostgreSQL 17.',
        'consequences' => 'Requires managing migrations.',
    ];

    FunWorkServer::actingAs($user)
        ->tool(LogDecisionTool::class, $firstPayload)
        ->assertOk()
        ->assertName('log_decision')
        ->assertSee([
            'ADR-001',
            'Use PostgreSQL for analytics',
            'Proposed',
            'We need JSONB queries.',
            'Adopt PostgreSQL 17.',
            'Requires managing migrations.',
        ]);

    FunWorkServer::actingAs($user)
        ->tool(LogDecisionTool::class, [
            'project_id' => $project->id,
            'title' => 'Use Redis for cache',
            'context' => 'Low-latency reads needed.',
            'decision' => 'Use Redis 7.',
            'consequences' => 'Extra container in compose.',
            'status' => 'Accepted',
            'decided_at' => '2026-11-10',
        ])
        ->assertOk()
        ->assertSee([
            'ADR-002',
            'Use Redis for cache',
            'Accepted',
            '2026-11-10',
        ]);

    $decisions = Decision::where('project_id', $project->id)->orderBy('number')->get();

    expect($decisions)->toHaveCount(2)
        ->and($decisions[0]->number)->toBe(1)
        ->and($decisions[0]->code)->toBe('ADR-001')
        ->and($decisions[0]->created_by)->toBe($user->id)
        ->and($decisions[1]->number)->toBe(2)
        ->and($decisions[1]->code)->toBe('ADR-002');
});

test('log_decision defaults status to Proposed with null decided_at and sets today for Accepted or Rejected', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $today = now('UTC')->toDateString();

    FunWorkServer::actingAs($user)
        ->tool(LogDecisionTool::class, [
            'project_id' => $project->id,
            'title' => 'Proposed default decision',
            'context' => 'Context',
            'decision' => 'Decision',
            'consequences' => 'Consequences',
        ])
        ->assertOk()
        ->assertSee('Proposed');

    FunWorkServer::actingAs($user)
        ->tool(LogDecisionTool::class, [
            'project_id' => $project->id,
            'title' => 'Accepted decision without date',
            'context' => 'Context',
            'decision' => 'Decision',
            'consequences' => 'Consequences',
            'status' => 'Accepted',
        ])
        ->assertOk()
        ->assertSee('Accepted')
        ->assertSee($today);

    FunWorkServer::actingAs($user)
        ->tool(LogDecisionTool::class, [
            'project_id' => $project->id,
            'title' => 'Rejected decision without date',
            'context' => 'Context',
            'decision' => 'Decision',
            'consequences' => 'Consequences',
            'status' => 'Rejected',
        ])
        ->assertOk()
        ->assertSee('Rejected')
        ->assertSee($today);

    $proposed = Decision::where('title', 'Proposed default decision')->firstOrFail();
    $accepted = Decision::where('title', 'Accepted decision without date')->firstOrFail();
    $rejected = Decision::where('title', 'Rejected decision without date')->firstOrFail();

    expect($proposed->status)->toBe(DecisionStatus::Proposed)
        ->and($proposed->decided_at)->toBeNull()
        ->and($accepted->status)->toBe(DecisionStatus::Accepted)
        ->and($accepted->decided_at?->format('Y-m-d'))->toBe($today)
        ->and($rejected->status)->toBe(DecisionStatus::Rejected)
        ->and($rejected->decided_at?->format('Y-m-d'))->toBe($today);
});

test('log_decision rejects Superseded and invalid statuses', function (string $status) {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    FunWorkServer::actingAs($user)
        ->tool(LogDecisionTool::class, [
            'project_id' => $project->id,
            'title' => 'Invalid status ADR',
            'context' => 'Context',
            'decision' => 'Decision',
            'consequences' => 'Consequences',
            'status' => $status,
        ])
        ->assertHasErrors();

    expect(Decision::where('project_id', $project->id)->exists())->toBeFalse();
})->with([
    'superseded' => DecisionStatus::Superseded->value,
    'invalid' => 'Approved',
]);

test('log_decision rejects missing required fields', function (string $missingField) {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $payload = [
        'project_id' => $project->id,
        'title' => 'Valid title',
        'context' => 'Valid context',
        'decision' => 'Valid decision',
        'consequences' => 'Valid consequences',
    ];

    unset($payload[$missingField]);

    FunWorkServer::actingAs($user)
        ->tool(LogDecisionTool::class, $payload)
        ->assertHasErrors();

    expect(Decision::count())->toBe(0);
})->with([
    'project_id' => 'project_id',
    'title' => 'title',
    'context' => 'context',
    'decision' => 'decision',
    'consequences' => 'consequences',
]);

test('log_decision rejects creating a decision in another user project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    FunWorkServer::actingAs($intruder)
        ->tool(LogDecisionTool::class, [
            'project_id' => $project->id,
            'title' => 'Unauthorized ADR',
            'context' => 'Context',
            'decision' => 'Decision',
            'consequences' => 'Consequences',
        ])
        ->assertHasErrors(['You are not authorized to create decisions in this project.']);

    expect(Decision::where('project_id', $project->id)->exists())->toBeFalse();
});

test('log_decision rejects unauthenticated requests', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    FunWorkServer::tool(LogDecisionTool::class, [
        'project_id' => $project->id,
        'title' => 'Unauthenticated ADR',
        'context' => 'Context',
        'decision' => 'Decision',
        'consequences' => 'Consequences',
    ])
        ->assertHasErrors(['Unauthenticated.']);

    expect(Decision::where('project_id', $project->id)->exists())->toBeFalse();
});

test('list_decisions returns only the project decisions ordered by number and filters by status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $otherProject = Project::factory()->create(['owner_id' => $user->id]);

    Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'number' => 2,
        'title' => 'Second ADR',
        'status' => DecisionStatus::Accepted,
        'decided_at' => '2026-11-12',
    ]);

    Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'number' => 1,
        'title' => 'First ADR',
        'status' => DecisionStatus::Proposed,
        'decided_at' => null,
    ]);

    Decision::factory()->create([
        'project_id' => $otherProject->id,
        'created_by' => $user->id,
        'number' => 1,
        'title' => 'Other Project ADR',
        'status' => DecisionStatus::Accepted,
    ]);

    $created = app(CreateApiTokenAction::class)($user, 'ADR Reader');

    $allResponse = $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list_decisions',
                'arguments' => [
                    'project_id' => $project->id,
                ],
            ],
        ])
        ->assertSuccessful();

    $payloadText = $allResponse->json('result.content.0.text');
    $decoded = json_decode((string) $payloadText, true, 512, JSON_THROW_ON_ERROR);

    expect(array_column($decoded['decisions'], 'code'))->toBe(['ADR-001', 'ADR-002'])
        ->and(array_column($decoded['decisions'], 'title'))->toBe(['First ADR', 'Second ADR'])
        ->and($decoded['decisions'][0]['status'])->toBe('Proposed')
        ->and($decoded['decisions'][0]['decided_at'])->toBeNull()
        ->and($decoded['decisions'][1]['status'])->toBe('Accepted')
        ->and($decoded['decisions'][1]['decided_at'])->toBe('2026-11-12')
        ->and($payloadText)->not->toContain('Other Project ADR');

    FunWorkServer::actingAs($user)
        ->tool(ListDecisionsTool::class, [
            'project_id' => $project->id,
            'status' => DecisionStatus::Accepted->value,
        ])
        ->assertOk()
        ->assertName('list_decisions')
        ->assertSee(['ADR-002', 'Second ADR'])
        ->assertDontSee(['ADR-001', 'First ADR', 'Other Project ADR']);
});

test('list_decisions rejects accessing another user project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    FunWorkServer::actingAs($intruder)
        ->tool(ListDecisionsTool::class, [
            'project_id' => $project->id,
        ])
        ->assertHasErrors(['You are not authorized to access this project.']);
});

test('list_decisions rejects unauthenticated requests', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    FunWorkServer::tool(ListDecisionsTool::class, [
        'project_id' => $project->id,
    ])
        ->assertHasErrors(['Unauthenticated.']);
});
