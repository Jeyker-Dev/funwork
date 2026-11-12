<?php

use App\Actions\Projects\CreateDefaultAreasAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Servers\FunWorkServer;
use App\Mcp\Tools\AddInboxItemTool;
use App\Mcp\Tools\AddTaskTool;
use App\Mcp\Tools\GetTaskTool;
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

test('list_projects returns only the authenticated user non-archived projects with their areas', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $activeProject = Project::factory()->create([
        'owner_id' => $userA->id,
        'name' => 'FunWork Active',
        'archived_at' => null,
    ]);
    app(CreateDefaultAreasAction::class)->execute($activeProject);

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
        ->assertSee('backend')
        ->assertDontSee('FunWork Archived')
        ->assertDontSee('Other User Project');
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

test('list_tasks filters tasks by status and area slug', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)->execute($project);

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
            'status' => 'Todo',
            'area' => 'BACKEND',
        ])
        ->assertOk()
        ->assertSee('Backend Todo Task')
        ->assertDontSee('Frontend Doing Task')
        ->assertDontSee('Unassigned Area Task');

    FunWorkServer::actingAs($user)
        ->tool(ListTasksTool::class, [
            'project_id' => $project->id,
            'area' => ListTasksTool::NO_AREA_FILTER,
        ])
        ->assertOk()
        ->assertSee('Unassigned Area Task')
        ->assertDontSee('Backend Todo Task');
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
    app(CreateDefaultAreasAction::class)->execute($project);
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
    app(CreateDefaultAreasAction::class)->execute($project);
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

test('add_task creates a task with area resolution, position calculation, and user assignment', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)->execute($project);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'position' => 3,
    ]);

    FunWorkServer::actingAs($user)
        ->tool(AddTaskTool::class, [
            'project_id' => $project->id,
            'title' => 'New MCP Task',
            'description' => 'Created from Antigravity',
            'area' => 'Backend',
            'status' => 'Todo',
            'priority' => 'Medium',
            'due_date' => '2026-11-20',
        ])
        ->assertOk()
        ->assertSee('New MCP Task')
        ->assertSee('backend');

    $createdTask = Task::where('title', 'New MCP Task')->first();

    expect($createdTask)
        ->not->toBeNull()
        ->project_id->toBe($project->id)
        ->status->toBe(TaskStatus::Todo)
        ->priority->toBe(TaskPriority::Medium)
        ->position->toBe(4)
        ->area_id->toBe($project->areaByKey('backend')->id)
        ->and($createdTask->users->pluck('id')->all())->toBe([$user->id]);
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
