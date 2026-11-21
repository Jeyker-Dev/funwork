<?php

use App\Actions\Auth\CreateApiTokenAction;
use App\Actions\Projects\CreateDefaultAreasAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Servers\FunWorkServer;
use App\Mcp\Tools\AddInboxItemTool;
use App\Mcp\Tools\AddTaskTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListInboxItemsTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\ReadNotebookTool;
use App\Mcp\Tools\UpdateTaskStatusTool;
use App\Models\InboxItem;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

test('list_projects returns only the authenticated user non-archived projects', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Project::factory()->create([
        'owner_id' => $userA->id,
        'name' => 'FunWork Active',
        'archived_at' => null,
    ]);

    Project::factory()->create([
        'owner_id' => $userA->id,
        'name' => 'FunWork Archived',
        'archived_at' => now(),
    ]);

    Project::factory()->create([
        'owner_id' => $userB->id,
        'name' => 'Other User Project',
    ]);

    FunWorkServer::actingAs($userA)
        ->tool(ListProjectsTool::class)
        ->assertOk()
        ->assertSee('FunWork Active')
        ->assertDontSee('FunWork Archived')
        ->assertDontSee('Other User Project');
});

test('list_projects includes each project configured areas', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'FunWork Active',
        'archived_at' => null,
    ]);
    app(CreateDefaultAreasAction::class)($project);

    FunWorkServer::actingAs($user)
        ->tool(ListProjectsTool::class)
        ->assertOk()
        ->assertSee('backend');
});

test('list_projects includes archived projects of the authenticated user when requested', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Project::factory()->create([
        'owner_id' => $userA->id,
        'name' => 'FunWork Active',
        'archived_at' => null,
    ]);

    Project::factory()->create([
        'owner_id' => $userA->id,
        'name' => 'FunWork Archived',
        'archived_at' => now(),
    ]);

    Project::factory()->create([
        'owner_id' => $userB->id,
        'name' => 'Other User Project',
    ]);

    FunWorkServer::actingAs($userA)
        ->tool(ListProjectsTool::class, ['include_archived' => true])
        ->assertOk()
        ->assertSee('FunWork Active')
        ->assertSee('FunWork Archived')
        ->assertDontSee('Other User Project');
});

test('list_tasks filters tasks by status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Todo Task',
        'status' => TaskStatus::Todo,
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Doing Task',
        'status' => TaskStatus::Doing,
    ]);

    FunWorkServer::actingAs($user)
        ->tool(ListTasksTool::class, [
            'project_id' => $project->id,
            'status' => 'Todo',
        ])
        ->assertOk()
        ->assertSee('Todo Task')
        ->assertDontSee('Doing Task');
});

test('list_tasks filters tasks by area slug case-insensitively', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);

    $backendArea = $project->areaByKey('backend');
    $frontendArea = $project->areaByKey('frontend');

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Backend Todo Task',
        'status' => TaskStatus::Todo,
        'area_id' => $backendArea->id,
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Frontend Doing Task',
        'status' => TaskStatus::Doing,
        'area_id' => $frontendArea->id,
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Unassigned Area Task',
        'status' => TaskStatus::Todo,
        'area_id' => null,
    ]);

    FunWorkServer::actingAs($user)
        ->tool(ListTasksTool::class, [
            'project_id' => $project->id,
            'area' => 'BACKEND',
        ])
        ->assertOk()
        ->assertSee('Backend Todo Task')
        ->assertDontSee('Frontend Doing Task')
        ->assertDontSee('Unassigned Area Task');
});

test('list_tasks filters unassigned tasks when area is none', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);

    $backendArea = $project->areaByKey('backend');

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Backend Todo Task',
        'status' => TaskStatus::Todo,
        'area_id' => $backendArea->id,
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Unassigned Area Task',
        'status' => TaskStatus::Todo,
        'area_id' => null,
    ]);

    FunWorkServer::actingAs($user)
        ->tool(ListTasksTool::class, [
            'project_id' => $project->id,
            'area' => ListTasksTool::NO_AREA_FILTER,
        ])
        ->assertOk()
        ->assertSee('Unassigned Area Task')
        ->assertDontSee('Backend Todo Task');
});

test('list_tasks returns an error when the area key is unknown', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);

    FunWorkServer::actingAs($user)
        ->tool(ListTasksTool::class, [
            'project_id' => $project->id,
            'area' => 'nonexistent',
        ])
        ->assertHasErrors(["Area [nonexistent] not found in project [{$project->id}]."]);
});

test('list_tasks rejects access to a project owned by another user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $project = Project::factory()->create(['owner_id' => $userA->id]);

    FunWorkServer::actingAs($userB)
        ->tool(ListTasksTool::class, [
            'project_id' => $project->id,
        ])
        ->assertHasErrors(['You are not authorized to access this project.']);
});

test('get_task returns the full task details for the project owner', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);
    $backendArea = $project->areaByKey('backend');

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Implement MCP server',
        'description' => 'Antigravity lists and updates tasks',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::High,
        'area_id' => $backendArea->id,
    ]);

    FunWorkServer::actingAs($user)
        ->tool(GetTaskTool::class, ['task_id' => $task->id])
        ->assertOk()
        ->assertSee('Implement MCP server')
        ->assertSee('Antigravity lists and updates tasks')
        ->assertSee('backend');
});

test('get_task rejects viewing a task owned by another user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $project = Project::factory()->create(['owner_id' => $userA->id]);
    $task = Task::factory()->create(['project_id' => $project->id]);

    FunWorkServer::actingAs($userB)
        ->tool(GetTaskTool::class, ['task_id' => $task->id])
        ->assertHasErrors(['You are not authorized to view this task.']);
});

test('update_task_status transitions the task status for the project owner', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);
    $backendArea = $project->areaByKey('backend');

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Status Task',
        'description' => 'Detailed brief for status update',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::High,
        'area_id' => $backendArea->id,
    ]);

    FunWorkServer::actingAs($user)
        ->tool(UpdateTaskStatusTool::class, [
            'task_id' => $task->id,
            'status' => 'Doing',
        ])
        ->assertOk()
        ->assertSee('Doing')
        ->assertSee('Status Task')
        ->assertSee('Detailed brief for status update')
        ->assertSee('High')
        ->assertSee('backend');

    expect($task->fresh()->status)->toBe(TaskStatus::Doing);
});

test('update_task_status rejects an invalid task status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
    ]);

    FunWorkServer::actingAs($user)
        ->tool(UpdateTaskStatusTool::class, [
            'task_id' => $task->id,
            'status' => 'invalid-status',
        ])
        ->assertHasErrors();

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

test('update_task_status rejects updating a task owned by another user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $project = Project::factory()->create(['owner_id' => $userA->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
    ]);

    FunWorkServer::actingAs($userB)
        ->tool(UpdateTaskStatusTool::class, [
            'task_id' => $task->id,
            'status' => 'Done',
        ])
        ->assertHasErrors(['You are not authorized to update this task.']);

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

test('add_task persists the created task attributes', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    FunWorkServer::actingAs($user)
        ->tool(AddTaskTool::class, [
            'project_id' => $project->id,
            'title' => 'New MCP Task',
            'description' => 'Created from Antigravity',
            'status' => 'Todo',
            'priority' => 'Medium',
            'due_date' => '2026-11-20',
        ])
        ->assertOk()
        ->assertSee('New MCP Task');

    $createdTask = Task::where('title', 'New MCP Task')->first();

    expect($createdTask)
        ->not->toBeNull()
        ->project_id->toBe($project->id)
        ->status->toBe(TaskStatus::Todo)
        ->priority->toBe(TaskPriority::Medium)
        ->and($createdTask->due_date?->toDateString())->toBe('2026-11-20');
});

test('add_task resolves the area case-insensitively', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);

    FunWorkServer::actingAs($user)
        ->tool(AddTaskTool::class, [
            'project_id' => $project->id,
            'title' => 'Area MCP Task',
            'description' => 'Resolves area key',
            'area' => 'Backend',
        ])
        ->assertOk()
        ->assertSee('backend');

    $createdTask = Task::where('title', 'Area MCP Task')->first();

    expect($createdTask)
        ->not->toBeNull()
        ->area_id->toBe($project->areaByKey('backend')->id);
});

test('add_task calculates the next position within the target status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'position' => 3,
    ]);

    FunWorkServer::actingAs($user)
        ->tool(AddTaskTool::class, [
            'project_id' => $project->id,
            'title' => 'Positioned MCP Task',
            'description' => 'Checks position calculation',
            'status' => 'Todo',
        ])
        ->assertOk();

    expect(Task::where('title', 'Positioned MCP Task')->first())
        ->not->toBeNull()
        ->position->toBe(4);
});

test('add_task assigns the authenticated user to the created task', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    FunWorkServer::actingAs($user)
        ->tool(AddTaskTool::class, [
            'project_id' => $project->id,
            'title' => 'Assigned MCP Task',
            'description' => 'Checks user assignment',
        ])
        ->assertOk();

    $createdTask = Task::where('title', 'Assigned MCP Task')->first();

    expect($createdTask)
        ->not->toBeNull()
        ->and($createdTask->users->pluck('id')->all())->toBe([$user->id]);
});

test('add_task returns an error and creates no task when the area key is unknown', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);

    FunWorkServer::actingAs($user)
        ->tool(AddTaskTool::class, [
            'project_id' => $project->id,
            'title' => 'Unknown Area Task',
            'description' => 'Should fail without creating a task',
            'area' => 'nonexistent',
        ])
        ->assertHasErrors(["Area [nonexistent] not found in project [{$project->id}]."]);

    expect(Task::where('title', 'Unknown Area Task')->exists())->toBeFalse();
});

test('add_task rejects creating a task in a project owned by another user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $project = Project::factory()->create(['owner_id' => $userA->id]);

    FunWorkServer::actingAs($userB)
        ->tool(AddTaskTool::class, [
            'project_id' => $project->id,
            'title' => 'Unauthorized Task',
            'description' => 'Should fail',
        ])
        ->assertHasErrors(['You are not authorized to create tasks in this project.']);

    expect(Task::where('title', 'Unauthorized Task')->exists())->toBeFalse();
});

test('add_task rejects an unauthorized user even when the area key is unknown', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $project = Project::factory()->create(['owner_id' => $userA->id]);

    FunWorkServer::actingAs($userB)
        ->tool(AddTaskTool::class, [
            'project_id' => $project->id,
            'title' => 'Unauthorized Unknown Area Task',
            'description' => 'Should fail authorization before area lookup',
            'area' => 'nonexistent',
        ])
        ->assertHasErrors(['You are not authorized to create tasks in this project.']);

    expect(Task::where('title', 'Unauthorized Unknown Area Task')->exists())->toBeFalse();
});

test('add_inbox_item captures an inbox item for the project owner', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    FunWorkServer::actingAs($user)
        ->tool(AddInboxItemTool::class, [
            'project_id' => $project->id,
            'content' => 'Quick idea from Antigravity',
        ])
        ->assertOk()
        ->assertSee('Quick idea from Antigravity');

    expect(InboxItem::where('project_id', $project->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

test('add_inbox_item rejects adding an inbox item to a project owned by another user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $project = Project::factory()->create(['owner_id' => $userA->id]);

    FunWorkServer::actingAs($userB)
        ->tool(AddInboxItemTool::class, [
            'project_id' => $project->id,
            'content' => 'Intruder idea',
        ])
        ->assertHasErrors(['You are not authorized to add inbox items to this project.']);

    expect(InboxItem::where('project_id', $project->id)->exists())->toBeFalse();
});

test('FunWorkServer registers list_inbox_items', function () {
    $user = User::factory()->create();
    $created = app(CreateApiTokenAction::class)($user, 'Inbox Client');

    $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'list_inbox_items']);
});

test('list_inbox_items returns inbox items newest first and excludes soft-deleted items', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $olderItem = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Older inbox note',
        'created_at' => now()->subHour(),
    ]);

    $newerItem = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Newer inbox note',
        'created_at' => now(),
    ]);

    $deletedItem = InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Deleted inbox note',
    ]);
    $deletedItem->delete();

    $token = app(CreateApiTokenAction::class)($user, 'Inbox Order Client')->plainTextToken;

    $httpResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list_inbox_items',
                'arguments' => ['project_id' => $project->id],
            ],
        ])
        ->assertSuccessful();

    $payload = json_decode($httpResponse->json('result.content.0.text'), true);

    expect(array_column($payload['inbox_items'], 'id'))->toBe([$newerItem->id, $olderItem->id]);
});

test('list_inbox_items scopes results to the requested project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $otherProject = Project::factory()->create(['owner_id' => $user->id]);

    InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Target project note',
    ]);

    InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $otherProject->id,
        'content' => 'Other project note',
    ]);

    FunWorkServer::actingAs($user)
        ->tool(ListInboxItemsTool::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertName('list_inbox_items')
        ->assertSee('Target project note')
        ->assertDontSee('Other project note');
});

test('list_inbox_items scopes results to the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    InboxItem::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'content' => 'Owner inbox note',
    ]);

    InboxItem::factory()->create([
        'user_id' => $otherUser->id,
        'project_id' => $project->id,
        'content' => 'Other user note',
    ]);

    FunWorkServer::actingAs($user)
        ->tool(ListInboxItemsTool::class, ['project_id' => $project->id])
        ->assertOk()
        ->assertSee('Owner inbox note')
        ->assertDontSee('Other user note');
});

test('list_inbox_items rejects listing inbox items of a project owned by another user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $userA->id]);

    InboxItem::factory()->create([
        'user_id' => $userA->id,
        'project_id' => $project->id,
        'content' => 'Private note',
    ]);

    FunWorkServer::actingAs($userB)
        ->tool(ListInboxItemsTool::class, [
            'project_id' => $project->id,
        ])
        ->assertHasErrors(['You are not authorized to access this project.']);
});

test('list_inbox_items returns Project not found for an unknown project id', function () {
    $user = User::factory()->create();

    FunWorkServer::actingAs($user)
        ->tool(ListInboxItemsTool::class, [
            'project_id' => 999999,
        ])
        ->assertHasErrors(['Project not found.']);
});

test('read_notebook returns exported Markdown by project and name for the owner', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Docs',
        'description' => 'Mirrored repository documentation',
    ]);

    NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'created_by' => $user->id,
        'order' => 1,
        'content' => [
            'title' => 'docs/00-vision.md',
            'body' => 'FunWork is a context hub.',
        ],
    ]);

    FunWorkServer::actingAs($user)
        ->tool(ReadNotebookTool::class, [
            'project_id' => $project->id,
            'name' => 'Docs',
        ])
        ->assertOk()
        ->assertSee('docs/00-vision.md')
        ->assertSee('FunWork is a context hub.');
});

test('read_notebook rejects reading a notebook owned by another user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $project = Project::factory()->create(['owner_id' => $userA->id]);
    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $userA->id,
    ]);

    FunWorkServer::actingAs($userB)
        ->tool(ReadNotebookTool::class, [
            'notebook_id' => $notebook->id,
        ])
        ->assertHasErrors(['You are not authorized to view this notebook.']);
});
