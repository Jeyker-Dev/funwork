<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('owner can render task detail page and see initial data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Initial Title',
        'description' => 'Initial Description',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Medium,
        'due_date' => '2026-11-20',
    ]);
    $task->users()->attach($user->id);

    Livewire::actingAs($user)
        ->test('pages::projects.tasks.show', ['project' => $project, 'task' => $task])
        ->assertOk()
        ->assertSee('Initial Title')
        ->assertSee('Initial Description')
        ->assertSee('Todo')
        ->assertSee('Medium')
        ->assertSet('form.title', 'Initial Title')
        ->assertSet('form.description', 'Initial Description')
        ->assertSet('form.status', TaskStatus::Todo->value)
        ->assertSet('form.priority', TaskPriority::Medium->value)
        ->assertSet('form.dueDate', '2026-11-20')
        ->assertSet('form.assignedUserIds', [$user->id]);
});

test('owner successfully edits task fields and changes persist in database', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Old Title',
        'description' => 'Old Description',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Low,
        'due_date' => '2026-10-10',
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.tasks.show', ['project' => $project, 'task' => $task])
        ->set('form.title', 'Updated Task Title')
        ->set('form.description', 'Updated Task Description')
        ->set('form.status', TaskStatus::Done->value)
        ->set('form.priority', TaskPriority::High->value)
        ->set('form.dueDate', '2026-12-31')
        ->set('form.assignedUserIds', [$user->id])
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'project_id' => $project->id,
        'title' => 'Updated Task Title',
        'description' => 'Updated Task Description',
        'status' => TaskStatus::Done->value,
        'priority' => TaskPriority::High->value,
    ]);

    $task->refresh();
    expect($task->title)->toBe('Updated Task Title')
        ->and($task->description)->toBe('Updated Task Description')
        ->and($task->status)->toBe(TaskStatus::Done)
        ->and($task->priority)->toBe(TaskPriority::High)
        ->and($task->due_date?->format('Y-m-d'))->toBe('2026-12-31')
        ->and($task->users->pluck('id')->all())->toBe([$user->id]);
});

test('submitting invalid status or priority fails validation and does not alter record', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Original Task',
        'description' => 'Original Description',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Low,
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.tasks.show', ['project' => $project, 'task' => $task])
        ->set('form.status', 'InvalidStatusValue')
        ->set('form.priority', 'InvalidPriorityValue')
        ->call('save')
        ->assertHasErrors(['form.status', 'form.priority']);

    $task->refresh();
    expect($task->title)->toBe('Original Task')
        ->and($task->status)->toBe(TaskStatus::Todo)
        ->and($task->priority)->toBe(TaskPriority::Low);
});

test('unauthorized user receives 403 forbidden when attempting to mount or invoke save directly', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Protected Task',
        'description' => 'Secret Description',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Medium,
    ]);

    Livewire::actingAs($intruder)
        ->test('pages::projects.tasks.show', ['project' => $project, 'task' => $task])
        ->assertForbidden();

    $task->refresh();
    expect($task->title)->toBe('Protected Task');
});

test('tasks index page renders successfully via route and component with project sidebar', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('project.tasks', $project));
    $response->assertOk()
        ->assertSee('Your Tasks,')
        ->assertSee(route('projects.show', ['project' => $project]))
        ->assertSee(route('project.inbox', ['project' => $project]))
        ->assertSee(route('project.tasks', ['project' => $project]))
        ->assertSee(route('project.notebooks', ['project' => $project]))
        ->assertSee(route('project.files', ['project' => $project]));

    Livewire::actingAs($user)
        ->test('pages::projects.tasks.index', ['project' => $project])
        ->assertOk()
        ->assertSee('Your Tasks,')
        ->assertSee('Organized.');
});

test('task detail HTTP route renders successfully with project sidebar', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Sidebar Task Check',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Medium,
    ]);

    $response = $this->actingAs($user)->get(route('show.task', [$project, $task]));
    $response->assertOk()
        ->assertSee('Sidebar Task Check')
        ->assertSee('Edit Task')
        ->assertSee('Save changes')
        ->assertSee(route('projects.show', ['project' => $project]))
        ->assertSee(route('project.inbox', ['project' => $project]))
        ->assertSee(route('project.tasks', ['project' => $project]))
        ->assertSee(route('project.notebooks', ['project' => $project]))
        ->assertSee(route('project.files', ['project' => $project]));
});

test('owner can delete task, cascades task_user rows and redirects to project tasks', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Task to be deleted',
    ]);
    $assignedUser = User::factory()->create();
    $task->users()->attach([$user->id, $assignedUser->id]);

    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    $this->assertDatabaseHas('task_user', ['task_id' => $task->id, 'user_id' => $user->id]);
    $this->assertDatabaseHas('task_user', ['task_id' => $task->id, 'user_id' => $assignedUser->id]);

    Livewire::actingAs($user)
        ->test('pages::projects.tasks.show', ['project' => $project, 'task' => $task])
        ->assertSee('Delete task')
        ->call('delete')
        ->assertRedirect(route('project.tasks', $project));

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    $this->assertDatabaseMissing('task_user', ['task_id' => $task->id]);
});

test('unauthorized user receives 403 forbidden when mounting component and task persists in database', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Intruder Cannot Touch',
    ]);

    Livewire::actingAs($intruder)
        ->test('pages::projects.tasks.show', ['project' => $project, 'task' => $task])
        ->assertForbidden();

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => 'Intruder Cannot Touch',
    ]);
});

test('task detail page renders markdown html while form preserves raw markdown', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $rawMarkdown = "# Implementation Plan\n\nThis is **crucial**.\n\n```bash\nphp artisan test\n```";

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Markdown Task',
        'description' => $rawMarkdown,
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.tasks.show', ['project' => $project, 'task' => $task])
        ->assertOk()
        ->assertSeeHtml('<h1>Implementation Plan</h1>')
        ->assertSeeHtml('<strong>crucial</strong>')
        ->assertSeeHtml('markdown-content')
        ->assertSet('form.description', $rawMarkdown);
});
