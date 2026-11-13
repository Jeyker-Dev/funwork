<?php

use App\Enums\DecisionStatus;
use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('decision casts status enum, decided_at date, and formats ADR code', function () {
    $decision = Decision::factory()->accepted()->create([
        'number' => 7,
        'decided_at' => '2026-11-13',
    ]);

    expect($decision->status)->toBe(DecisionStatus::Accepted)
        ->and($decision->decided_at?->toDateString())->toBe('2026-11-13')
        ->and($decision->code)->toBe('ADR-007');
});

test('decision enforces unique number per project', function () {
    $project = Project::factory()->create();

    Decision::factory()->create([
        'project_id' => $project->id,
        'number' => 1,
    ]);

    expect(fn () => Decision::factory()->create([
        'project_id' => $project->id,
        'number' => 1,
    ]))->toThrow(QueryException::class);
});

test('decision belongs to project, creator, and superseding decision', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $replacement = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 2,
    ]);
    $original = Decision::factory()->superseded($replacement)->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
    ]);

    expect($original->project->id)->toBe($project->id)
        ->and($original->creator->id)->toBe($owner->id)
        ->and($original->supersededBy->id)->toBe($replacement->id)
        ->and($project->decisions()->count())->toBe(2);
});

test('decision markdown accessors render markdown and strip XSS payloads', function () {
    $decision = Decision::factory()->create([
        'context' => "## Context\n\n<script>alert(1)</script>\n\n[bad](javascript:alert(1))",
        'decision' => "Use **PostgreSQL**.\n\n<img src=x onerror=alert(1)>",
        'consequences' => "- Better JSON support\n- [safe](https://example.com)",
    ]);

    $contextHtml = (string) $decision->context_html;
    $decisionHtml = (string) $decision->decision_html;
    $consequencesHtml = (string) $decision->consequences_html;

    expect($contextHtml)->toContain('<h2>Context</h2>')
        ->and($contextHtml)->not->toContain('<script>')
        ->and($contextHtml)->not->toContain('javascript:')
        ->and($decisionHtml)->toContain('<strong>PostgreSQL</strong>')
        ->and($decisionHtml)->not->toContain('onerror')
        ->and($consequencesHtml)->toContain('<li>Better JSON support</li>')
        ->and($consequencesHtml)->toContain('href="https://example.com"');
});
