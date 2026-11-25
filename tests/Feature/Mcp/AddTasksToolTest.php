<?php

use App\Actions\Auth\CreateApiTokenAction;
use App\Actions\Projects\CreateDefaultAreasAction;
use App\Data\Mcp\AddTasksData;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Servers\FunWorkServer;
use App\Mcp\Tools\AddTasksTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

test('FunWorkServer registers add_tasks', function () {
    $user = User::factory()->create();
    $created = app(CreateApiTokenAction::class)($user, 'Batch Tasks Client');

    $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'add_tasks']);
});

test('add_tasks creates all tasks in one transaction with sequential positions and areas', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'position' => 2,
    ]);

    $created = app(CreateApiTokenAction::class)($user, 'Batch Tasks Writer');

    $response = $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'add_tasks',
                'arguments' => [
                    'project_id' => $project->id,
                    'tasks' => [
                        [
                            'title' => 'Design schema',
                            'description' => 'Create initial migrations',
                            'area' => 'DATABASE',
                            'priority' => TaskPriority::High->value,
                            'due_date' => '2026-12-01',
                        ],
                        [
                            'title' => 'Build API endpoint',
                            'description' => 'Expose project brief',
                            'area' => 'backend',
                            'status' => TaskStatus::Todo->value,
                            'priority' => TaskPriority::Medium->value,
                        ],
                        [
                            'title' => 'Implement landing UI',
                            'description' => 'Create initial dashboard view',
                            'status' => TaskStatus::Doing->value,
                        ],
                    ],
                ],
            ],
        ])
        ->assertSuccessful();

    $payload = json_decode((string) $response->json('result.content.0.text'), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['tasks'])->toHaveCount(3)
        ->and(array_column($payload['tasks'], 'title'))->toBe([
            'Design schema',
            'Build API endpoint',
            'Implement landing UI',
        ]);

    $createdTasks = Task::where('project_id', $project->id)
        ->whereIn('title', ['Design schema', 'Build API endpoint', 'Implement landing UI'])
        ->orderBy('id')
        ->get();

    expect($createdTasks)->toHaveCount(3)
        ->and($createdTasks[0]->position)->toBe(3)
        ->and($createdTasks[0]->area_id)->toBe($project->areaByKey('database')->id)
        ->and($createdTasks[0]->priority)->toBe(TaskPriority::High)
        ->and($createdTasks[0]->due_date?->format('Y-m-d'))->toBe('2026-12-01')
        ->and($createdTasks[0]->users->pluck('id')->all())->toBe([$user->id])
        ->and($createdTasks[1]->position)->toBe(4)
        ->and($createdTasks[1]->area_id)->toBe($project->areaByKey('backend')->id)
        ->and($createdTasks[2]->status)->toBe(TaskStatus::Doing)
        ->and($createdTasks[2]->position)->toBe(1)
        ->and($createdTasks[2]->area_id)->toBeNull();
});

test('add_tasks creates none when any task in the batch is invalid', function (array $invalidTask) {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    FunWorkServer::actingAs($user)
        ->tool(AddTasksTool::class, [
            'project_id' => $project->id,
            'tasks' => [
                [
                    'title' => 'Valid first task',
                    'description' => 'Must be rolled back / never created',
                ],
                $invalidTask,
            ],
        ])
        ->assertHasErrors();

    expect(Task::where('project_id', $project->id)->count())->toBe(0);
})->with([
    'missing title' => [['description' => 'No title']],
    'empty title' => [['title' => '', 'description' => 'Empty title']],
    'missing description' => [['title' => 'Valid title']],
    'invalid status' => [['title' => 'Valid', 'description' => 'Desc', 'status' => 'Archived']],
    'invalid priority' => [['title' => 'Valid', 'description' => 'Desc', 'priority' => 'Critical']],
    'invalid due_date' => [['title' => 'Valid', 'description' => 'Desc', 'due_date' => '2026/12/01']],
]);

test('add_tasks rejects an empty task list', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    FunWorkServer::actingAs($user)
        ->tool(AddTasksTool::class, [
            'project_id' => $project->id,
            'tasks' => [],
        ])
        ->assertHasErrors();

    expect(Task::where('project_id', $project->id)->count())->toBe(0);
});

test('add_tasks respects the maximum tasks per call limit', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $tooManyTasks = array_map(
        fn (int $index): array => [
            'title' => "Task {$index}",
            'description' => "Description {$index}",
        ],
        range(1, AddTasksData::MAX_TASKS_PER_CALL + 1),
    );

    FunWorkServer::actingAs($user)
        ->tool(AddTasksTool::class, [
            'project_id' => $project->id,
            'tasks' => $tooManyTasks,
        ])
        ->assertHasErrors();

    expect(Task::where('project_id', $project->id)->count())->toBe(0);

    $maxAllowedTasks = array_slice($tooManyTasks, 0, AddTasksData::MAX_TASKS_PER_CALL);

    FunWorkServer::actingAs($user)
        ->tool(AddTasksTool::class, [
            'project_id' => $project->id,
            'tasks' => $maxAllowedTasks,
        ])
        ->assertOk();

    expect(Task::where('project_id', $project->id)->count())->toBe(AddTasksData::MAX_TASKS_PER_CALL);
});

test('add_tasks rejects an unknown area slug and creates no tasks', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);

    FunWorkServer::actingAs($user)
        ->tool(AddTasksTool::class, [
            'project_id' => $project->id,
            'tasks' => [
                [
                    'title' => 'Valid Backend Task',
                    'description' => 'Should not persist when a later task fails',
                    'area' => 'backend',
                ],
                [
                    'title' => 'Unknown Area Task',
                    'description' => 'Invalid area slug',
                    'area' => 'nonexistent',
                ],
            ],
        ])
        ->assertHasErrors(["Area [nonexistent] not found in project [{$project->id}]."]);

    expect(Task::where('project_id', $project->id)->count())->toBe(0);
});

test('add_tasks rejects creating tasks in another user project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    FunWorkServer::actingAs($intruder)
        ->tool(AddTasksTool::class, [
            'project_id' => $project->id,
            'tasks' => [
                [
                    'title' => 'Unauthorized Task',
                    'description' => 'Should fail authorization',
                    'area' => 'nonexistent',
                ],
            ],
        ])
        ->assertHasErrors(['You are not authorized to create tasks in this project.']);

    expect(Task::where('project_id', $project->id)->count())->toBe(0);
});

test('add_tasks rejects unauthenticated requests', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    FunWorkServer::tool(AddTasksTool::class, [
        'project_id' => $project->id,
        'tasks' => [
            [
                'title' => 'Unauthenticated Task',
                'description' => 'Should fail authentication',
            ],
        ],
    ])
        ->assertHasErrors(['Unauthenticated.']);

    expect(Task::where('project_id', $project->id)->count())->toBe(0);
});
