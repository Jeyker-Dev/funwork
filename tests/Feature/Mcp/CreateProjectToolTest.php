<?php

use App\Actions\Auth\CreateApiTokenAction;
use App\Data\Projects\ProjectData;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Mcp\Servers\FunWorkServer;
use App\Mcp\Tools\CreateProjectTool;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

test('FunWorkServer registers create_project', function () {
    $user = User::factory()->create();
    $created = app(CreateApiTokenAction::class)($user, 'Project Creator');

    $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'create_project']);
});

test('create_project creates a project with default areas for the authenticated user', function () {
    $user = User::factory()->create();
    $created = app(CreateApiTokenAction::class)($user, 'Project Creator');

    $response = $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_project',
                'arguments' => [
                    'name' => 'Kickoff App',
                    'description' => 'Built from MCP kickoff.',
                    'type' => ProjectType::Programming->value,
                    'status' => ProjectStatus::Planning->value,
                ],
            ],
        ])
        ->assertSuccessful();

    $payload = json_decode((string) $response->json('result.content.0.text'), true, 512, JSON_THROW_ON_ERROR);
    $project = Project::with('areas')->find($payload['id']);

    expect($project)->not->toBeNull()
        ->and($project->owner_id)->toBe($user->id)
        ->and($project->name)->toBe('Kickoff App')
        ->and($project->description)->toBe('Built from MCP kickoff.')
        ->and($project->type)->toBe(ProjectType::Programming->value)
        ->and($project->status)->toBe(ProjectStatus::Planning->value)
        ->and($project->icon)->toBe(ProjectData::DEFAULT_ICON)
        ->and($payload['name'])->toBe('Kickoff App')
        ->and($payload['type'])->toBe(ProjectType::Programming->value)
        ->and($payload['status'])->toBe(ProjectStatus::Planning->value)
        ->and(array_column($payload['areas'], 'slug'))->toBe(['frontend', 'backend', 'database', 'design']);
});

test('create_project via MCP and via the web form produce the same areas and defaults', function () {
    $user = User::factory()->create();

    FunWorkServer::actingAs($user)
        ->tool(CreateProjectTool::class, [
            'name' => 'MCP Default Project',
            'description' => 'Shared defaults check',
        ])
        ->assertOk();

    Livewire::actingAs($user)
        ->test('projects.create-project', ['elementName' => ''])
        ->set('form.name', 'Web Default Project')
        ->set('form.description', 'Shared defaults check')
        ->set('form.icon', ProjectData::DEFAULT_ICON)
        ->set('form.type', ProjectData::DEFAULT_TYPE)
        ->set('form.status', ProjectData::DEFAULT_STATUS)
        ->set('form.ownerId', $user->id)
        ->call('save')
        ->assertHasNoErrors();

    $mcpProject = Project::with('areas')->where('name', 'MCP Default Project')->firstOrFail();
    $webProject = Project::with('areas')->where('name', 'Web Default Project')->firstOrFail();

    expect($mcpProject->icon)->toBe($webProject->icon)
        ->and($mcpProject->type)->toBe($webProject->type)
        ->and($mcpProject->status)->toBe($webProject->status)
        ->and($mcpProject->due_at)->toBe($webProject->due_at)
        ->and($mcpProject->archived_at)->toBe($webProject->archived_at)
        ->and($mcpProject->areas->map->only(['name', 'slug', 'color', 'position'])->all())
        ->toBe($webProject->areas->map->only(['name', 'slug', 'color', 'position'])->all());
});

test('create_project rejects unauthenticated requests', function () {
    FunWorkServer::tool(CreateProjectTool::class, [
        'name' => 'Unauthenticated Project',
    ])
        ->assertHasErrors(['Unauthenticated.']);

    expect(Project::count())->toBe(0);
});

test('create_project returns authorization error when policy denies creating projects', function () {
    $user = User::factory()->create();

    Gate::before(fn (): bool => false);

    FunWorkServer::actingAs($user)
        ->tool(CreateProjectTool::class, [
            'name' => 'Denied Project',
        ])
        ->assertHasErrors(['You are not authorized to create projects.']);

    expect(Project::count())->toBe(0);
});

test('create_project returns validation errors for missing or invalid fields', function (array $arguments) {
    $user = User::factory()->create();

    FunWorkServer::actingAs($user)
        ->tool(CreateProjectTool::class, $arguments)
        ->assertHasErrors();

    expect(Project::count())->toBe(0);
})->with([
    'missing name' => [[]],
    'empty name' => [['name' => '']],
    'name too long' => [['name' => str_repeat('a', 256)]],
    'invalid type' => [['name' => 'Valid', 'type' => 'invalid-type']],
    'invalid status' => [['name' => 'Valid', 'status' => 'invalid-status']],
]);
