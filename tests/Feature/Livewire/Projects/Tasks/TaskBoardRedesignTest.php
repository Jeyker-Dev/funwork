<?php

use App\Actions\Projects\CreateDefaultAreasAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('renders every task in each column without a 3-per-column cap and removes hero', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    for ($i = 1; $i <= 6; $i++) {
        Task::factory()->create([
            'project_id' => $project->id,
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Medium->value,
            'title' => "Uncapped Todo Task {$i}",
            'position' => $i,
        ]);
    }

    $page = $this->actingAs($user)->get(route('project.tasks', $project));
    $page->assertOk()
        ->assertDontSee('Your Tasks,')
        ->assertDontSee('Organized.');

    $component = Livewire::actingAs($user)
        ->test('projects.tasks.task-board', ['project' => $project])
        ->assertOk()
        ->assertDontSee('more tasks ...');

    for ($i = 1; $i <= 6; $i++) {
        $component->assertSee("Uncapped Todo Task {$i}");
    }
});

test('moving across columns updates status and moving within a column updates position', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $todoFirst = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'position' => 1,
        'title' => 'Todo First',
    ]);
    $todoSecond = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'position' => 2,
        'title' => 'Todo Second',
    ]);
    $doingFirst = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'position' => 1,
        'title' => 'Doing First',
    ]);

    // Move Todo Second to top of Todo (within column)
    Livewire::actingAs($user)
        ->test('projects.tasks.task-board', ['project' => $project])
        ->call('moveTask', $todoSecond->id, 0, TaskStatus::Todo->value)
        ->assertHasNoErrors();

    expect($todoSecond->refresh()->status)->toBe(TaskStatus::Todo)
        ->and($todoSecond->position)->toBe(1)
        ->and($todoFirst->refresh()->position)->toBe(2);

    // Move Todo First across to Doing at position 0 (ahead of Doing First)
    Livewire::actingAs($user)
        ->test('projects.tasks.task-board', ['project' => $project])
        ->call('moveTask', $todoFirst->id, 0, TaskStatus::Doing->value)
        ->assertHasNoErrors();

    expect($todoFirst->refresh()->status)->toBe(TaskStatus::Doing)
        ->and($todoFirst->position)->toBe(1)
        ->and($doingFirst->refresh()->status)->toBe(TaskStatus::Doing)
        ->and($doingFirst->position)->toBe(2);
});

test('an unauthorized user cannot move a task via task-board', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'position' => 1,
    ]);

    $component = Livewire::actingAs($owner)
        ->test('projects.tasks.task-board', ['project' => $project]);

    Livewire::actingAs($intruder);

    $component->call('moveTask', $task->id, 0, TaskStatus::Doing->value)
        ->assertForbidden();

    expect($task->refresh()->status)->toBe(TaskStatus::Todo)
        ->and($task->position)->toBe(1);
});

test('cards show due, overdue, and High priority only when applicable and never render hardcoded avatar', function () {
    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->travelTo(now('UTC')->setDate(2026, 11, 22)->setTime(12, 0));

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Low,
        'due_date' => null,
        'title' => 'Plain Routine Task',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::High,
        'due_date' => '2026-11-20',
        'title' => 'Urgent Overdue Task',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'priority' => TaskPriority::Medium,
        'due_date' => '2026-11-22',
        'title' => 'Due Today Task',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'priority' => TaskPriority::Low,
        'due_date' => '2026-11-18',
        'title' => 'Completed Past Task',
    ]);

    $component = Livewire::actingAs($user)
        ->test('projects.tasks.task-board', ['project' => $project])
        ->assertOk()
        ->assertSee('Plain Routine Task')
        ->assertSee('Urgent Overdue Task')
        ->assertSee('Due Today Task')
        ->assertSee('Completed Past Task')
        ->assertSee('High')
        ->assertSee('Overdue')
        ->assertSee('Due today')
        ->assertDontSee('Due:')
        ->assertDontSee('calebporzio')
        ->assertDontSee('Caleb Porzio');

    $columnsHtml = str($component->html())->between('<section', '</section>')->toString();

    expect($columnsHtml)->not->toContain('unavatar.io')
        ->and($columnsHtml)->not->toContain('>Low<')
        ->and($columnsHtml)->not->toContain('>Medium<')
        ->and($columnsHtml)->not->toContain('HIGH');
});

test('the detail panel saves title, status, area, priority, due date, and toggles markdown checklist without corruption', function () {
    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);
    $backend = $project->areas()->where('slug', 'backend')->first();

    $markdown = "Intro line\n\n- [ ] First item\n- [x] Second item\n\nClosing note";

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Original Panel Task',
        'description' => $markdown,
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Low,
        'due_date' => '2026-11-20',
        'area_id' => null,
    ]);

    $this->travelTo(now('UTC')->setDate(2026, 11, 22)->setTime(12, 0));

    $panel = Livewire::actingAs($user)
        ->test('projects.tasks.task-detail-panel', ['project' => $project, 'taskId' => $task->id])
        ->assertOk()
        ->assertSee('Nov 20, overdue')
        ->assertSeeHtml('type="checkbox"');

    // Toggle first checklist item without corrupting surrounding markdown
    $panel->call('toggleChecklistItem', 0)
        ->assertHasNoErrors();

    expect($task->refresh()->description)
        ->toBe("Intro line\n\n- [x] First item\n- [x] Second item\n\nClosing note");

    // Edit and save fields from panel
    $panel->set('form.title', 'Updated Panel Task')
        ->set('form.status', TaskStatus::Doing->value)
        ->set('form.areaId', $backend->id)
        ->set('form.priority', TaskPriority::High->value)
        ->set('form.dueDate', '2026-11-28')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('task-updated');

    $task->refresh();
    expect($task->title)->toBe('Updated Panel Task')
        ->and($task->status)->toBe(TaskStatus::Doing)
        ->and($task->area_id)->toBe($backend->id)
        ->and($task->priority)->toBe(TaskPriority::High)
        ->and($task->due_date?->format('Y-m-d'))->toBe('2026-11-28');
});

test('the detail panel deletes a task only with confirmation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Task To Delete',
    ]);

    $panel = Livewire::actingAs($user)
        ->test('projects.tasks.task-detail-panel', ['project' => $project, 'taskId' => $task->id])
        ->assertSet('confirmingDelete', false)
        ->call('requestDelete')
        ->assertSet('confirmingDelete', true);

    expect(Task::find($task->id))->not->toBeNull();

    $panel->call('deleteTask')
        ->assertDispatched('task-deleted');

    expect(Task::find($task->id))->toBeNull();
});

test('area filters apply to both board and list views', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);

    $frontend = $project->areas()->where('slug', 'frontend')->first();
    $backend = $project->areas()->where('slug', 'backend')->first();

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Frontend Board List Task',
        'status' => TaskStatus::Todo,
        'area_id' => $frontend->id,
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Backend Board List Task',
        'status' => TaskStatus::Doing,
        'area_id' => $backend->id,
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Unassigned Area Task',
        'status' => TaskStatus::Done,
        'area_id' => null,
    ]);

    // Board view filtered
    Livewire::actingAs($user)
        ->test('projects.tasks.task-board', ['project' => $project])
        ->call('setArea', 'frontend')
        ->assertSee('Frontend Board List Task')
        ->assertDontSee('Backend Board List Task')
        ->assertDontSee('Unassigned Area Task');

    // List view filtered
    Livewire::actingAs($user)
        ->test('projects.tasks.task-board', ['project' => $project])
        ->call('setView', 'list')
        ->call('setArea', 'backend')
        ->assertDontSee('Frontend Board List Task')
        ->assertSee('Backend Board List Task')
        ->assertDontSee('Unassigned Area Task')
        ->call('setArea', 'no-area')
        ->assertDontSee('Frontend Board List Task')
        ->assertDontSee('Backend Board List Task')
        ->assertSee('Unassigned Area Task');
});

test('column add action creates a task with that column status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('projects.tasks.task-board', ['project' => $project])
        ->call('openCreateModal', TaskStatus::Doing->value)
        ->assertDispatched('open-create-task', status: TaskStatus::Doing->value);

    Livewire::actingAs($user)
        ->test('projects.tasks.create-task', ['project' => $project, 'hideTrigger' => true])
        ->call('openForStatus', TaskStatus::Doing->value)
        ->assertSet('form.status', TaskStatus::Doing->value)
        ->set('form.title', 'Created In Doing')
        ->set('form.description', 'Doing column task')
        ->set('form.priority', TaskPriority::Medium->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('task-created');

    $this->assertDatabaseHas('tasks', [
        'project_id' => $project->id,
        'title' => 'Created In Doing',
        'status' => TaskStatus::Doing->value,
    ]);
});

test('opening a task from board or list opens the detail panel and closing clears it', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Inspectable Task',
        'description' => 'Detailed notes',
    ]);

    Livewire::actingAs($user)
        ->test('projects.tasks.task-board', ['project' => $project])
        ->assertSet('selectedTaskId', null)
        ->call('openTask', $task->id)
        ->assertSet('selectedTaskId', $task->id)
        ->assertSee('Delete task')
        ->assertSee('Detailed notes')
        ->call('closePanel')
        ->assertSet('selectedTaskId', null);
});

test('invalid move status increments board revision to revert without altering database', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'position' => 1,
    ]);

    Livewire::actingAs($user)
        ->test('projects.tasks.task-board', ['project' => $project])
        ->assertSet('boardRevision', 0)
        ->call('moveTask', $task->id, 0, 'BogusStatus')
        ->assertSet('boardRevision', 1);

    expect($task->refresh()->status)->toBe(TaskStatus::Todo)
        ->and($task->position)->toBe(1);
});

test('unauthorized user cannot mount or mutate a task through the detail panel', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Private Task',
    ]);

    Livewire::actingAs($intruder)
        ->test('projects.tasks.task-detail-panel', ['project' => $project, 'taskId' => $task->id])
        ->assertForbidden();

    expect($task->refresh()->title)->toBe('Private Task');
});

test('detail panel and show page open tasks with null description and save keeping description empty', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Task Without Description',
        'description' => null,
        'due_date' => null,
        'area_id' => null,
        'position' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('projects.tasks.task-detail-panel', ['project' => $project, 'taskId' => $task->id])
        ->assertOk()
        ->assertSet('form.description', '')
        ->assertSee('No description yet.')
        ->set('form.title', 'Task Updated Without Description')
        ->call('save')
        ->assertHasNoErrors();

    expect($task->refresh()->title)->toBe('Task Updated Without Description')
        ->and($task->description)->toBe('');

    $task->update(['description' => null]);

    Livewire::actingAs($user)
        ->test('pages::projects.tasks.show', ['project' => $project, 'task' => $task])
        ->assertOk()
        ->assertSet('form.description', '')
        ->set('form.title', 'Show Page Updated Without Description')
        ->call('save')
        ->assertHasNoErrors();

    expect($task->refresh()->title)->toBe('Show Page Updated Without Description')
        ->and($task->description)->toBe('');
});
