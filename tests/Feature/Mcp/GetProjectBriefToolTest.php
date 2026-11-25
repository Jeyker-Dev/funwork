<?php

use App\Actions\Auth\CreateApiTokenAction;
use App\Actions\Projects\BuildProjectContextAction;
use App\Enums\TaskStatus;
use App\Mcp\Servers\FunWorkServer;
use App\Mcp\Tools\GetProjectBriefTool;
use App\Models\Area;
use App\Models\Decision;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

test('FunWorkServer registers get_project_brief as a read-only tool', function () {
    $user = User::factory()->create();
    $created = app(CreateApiTokenAction::class)($user, 'Brief Client');

    $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'get_project_brief']);

    $attributes = (new ReflectionClass(GetProjectBriefTool::class))->getAttributes(IsReadOnly::class);

    expect($attributes)->toHaveCount(1);
});

test('get_project_brief returns the same Markdown brief as BuildProjectContextAction for the owner', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Brief MCP Project',
    ]);
    $area = Area::factory()->create([
        'project_id' => $project->id,
        'name' => 'Backend',
        'slug' => 'backend',
        'position' => 1,
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'area_id' => $area->id,
        'title' => 'Expose brief tool',
        'status' => TaskStatus::Todo,
    ]);
    Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'number' => 1,
        'title' => 'Use Markdown brief',
    ]);
    InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'content' => 'Verify brief output',
    ]);

    $expectedMarkdown = app(BuildProjectContextAction::class)($project, $owner);
    $created = app(CreateApiTokenAction::class)($owner, 'Brief Reader');

    $response = $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_project_brief',
                'arguments' => [
                    'project_id' => $project->id,
                ],
            ],
        ])
        ->assertSuccessful();

    expect($response->json('result.content.0.text'))->toBe($expectedMarkdown);

    FunWorkServer::actingAs($owner)
        ->tool(GetProjectBriefTool::class, [
            'project_id' => $project->id,
        ])
        ->assertOk()
        ->assertName('get_project_brief')
        ->assertSee($expectedMarkdown);
});

test('get_project_brief rejects accessing another user project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    FunWorkServer::actingAs($intruder)
        ->tool(GetProjectBriefTool::class, [
            'project_id' => $project->id,
        ])
        ->assertHasErrors(['You are not authorized to access this project.']);
});

test('get_project_brief returns validation error when project_id is missing', function () {
    $user = User::factory()->create();

    FunWorkServer::actingAs($user)
        ->tool(GetProjectBriefTool::class, [])
        ->assertHasErrors();
});

test('get_project_brief rejects unauthenticated requests', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    FunWorkServer::tool(GetProjectBriefTool::class, [
        'project_id' => $project->id,
    ])
        ->assertHasErrors(['Unauthenticated.']);
});

test('get_project_brief returns Project not found for unknown project_id', function () {
    $user = User::factory()->create();

    FunWorkServer::actingAs($user)
        ->tool(GetProjectBriefTool::class, [
            'project_id' => 999999,
        ])
        ->assertHasErrors(['Project not found.']);
});
