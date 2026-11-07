<?php

use App\Actions\Projects\CreateDefaultAreasAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\InboxItem;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('task creation accepts area of same project, accepts null, and rejects area of another project', function () {
    $user = User::factory()->create();
    $projectA = Project::factory()->create(['owner_id' => $user->id]);
    $projectB = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)->execute($projectA);
    app(CreateDefaultAreasAction::class)->execute($projectB);

    $areaA = $projectA->areas()->where('slug', 'backend')->first();
    $areaB = $projectB->areas()->where('slug', 'backend')->first();

    // 1. Accepts area of same project
    Livewire::actingAs($user)
        ->test('projects.tasks.⚡create-task', ['project' => $projectA])
        ->set('form.title', 'Task with area')
        ->set('form.description', 'Desc')
        ->set('form.status', TaskStatus::Todo->value)
        ->set('form.priority', TaskPriority::Low->value)
        ->set('form.areaId', $areaA->id)
        ->call('save')
        ->assertHasNoErrors();

    $task1 = Task::where('title', 'Task with area')->first();
    expect($task1)->not->toBeNull()
        ->and($task1->area_id)->toBe($areaA->id);

    // 2. Accepts no area
    Livewire::actingAs($user)
        ->test('projects.tasks.⚡create-task', ['project' => $projectA])
        ->set('form.title', 'Task without area')
        ->set('form.description', 'Desc')
        ->set('form.status', TaskStatus::Todo->value)
        ->set('form.priority', TaskPriority::Low->value)
        ->set('form.areaId', null)
        ->call('save')
        ->assertHasNoErrors();

    $task2 = Task::where('title', 'Task without area')->first();
    expect($task2)->not->toBeNull()
        ->and($task2->area_id)->toBeNull();

    // 3. Rejects area of another project
    Livewire::actingAs($user)
        ->test('projects.tasks.⚡create-task', ['project' => $projectA])
        ->set('form.title', 'Task with wrong area')
        ->set('form.description', 'Desc')
        ->set('form.status', TaskStatus::Todo->value)
        ->set('form.priority', TaskPriority::Low->value)
        ->set('form.areaId', $areaB->id)
        ->call('save')
        ->assertHasErrors(['form.areaId']);
});

test('task edit accepts area of same project, accepts null, and rejects area of another project', function () {
    $user = User::factory()->create();
    $projectA = Project::factory()->create(['owner_id' => $user->id]);
    $projectB = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)->execute($projectA);
    app(CreateDefaultAreasAction::class)->execute($projectB);

    $areaA = $projectA->areas()->where('slug', 'frontend')->first();
    $areaB = $projectB->areas()->where('slug', 'frontend')->first();

    $task = Task::factory()->create([
        'project_id' => $projectA->id,
        'title' => 'Initial Task',
        'status' => TaskStatus::Todo->value,
        'priority' => TaskPriority::Low->value,
        'area_id' => null,
    ]);

    // 1. Accepts area of same project
    Livewire::actingAs($user)
        ->test('pages::projects.tasks.show', ['project' => $projectA, 'task' => $task])
        ->set('form.areaId', $areaA->id)
        ->call('save')
        ->assertHasNoErrors();

    $task->refresh();
    expect($task->area_id)->toBe($areaA->id);

    // 2. Accepts null
    Livewire::actingAs($user)
        ->test('pages::projects.tasks.show', ['project' => $projectA, 'task' => $task])
        ->set('form.areaId', null)
        ->call('save')
        ->assertHasNoErrors();

    $task->refresh();
    expect($task->area_id)->toBeNull();

    // 3. Rejects area of another project
    Livewire::actingAs($user)
        ->test('pages::projects.tasks.show', ['project' => $projectA, 'task' => $task])
        ->set('form.areaId', $areaB->id)
        ->call('save')
        ->assertHasErrors(['form.areaId']);
});

test('notebook creation accepts area of same project, accepts null, and rejects area of another project', function () {
    $user = User::factory()->create();
    $projectA = Project::factory()->create(['owner_id' => $user->id]);
    $projectB = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)->execute($projectA);
    app(CreateDefaultAreasAction::class)->execute($projectB);

    $areaA = $projectA->areas()->where('slug', 'database')->first();
    $areaB = $projectB->areas()->where('slug', 'database')->first();

    // 1. Accepts area of same project
    Livewire::actingAs($user)
        ->test('projects.notebooks.⚡create-notebook', ['project' => $projectA])
        ->set('form.name', 'DB Notes')
        ->set('form.description', 'Desc')
        ->set('form.areaId', $areaA->id)
        ->call('save')
        ->assertHasNoErrors();

    $nb1 = Notebook::where('name', 'DB Notes')->first();
    expect($nb1)->not->toBeNull()
        ->and($nb1->area_id)->toBe($areaA->id);

    // 2. Accepts null
    Livewire::actingAs($user)
        ->test('projects.notebooks.⚡create-notebook', ['project' => $projectA])
        ->set('form.name', 'General Notes')
        ->set('form.description', 'Desc')
        ->set('form.areaId', null)
        ->call('save')
        ->assertHasNoErrors();

    $nb2 = Notebook::where('name', 'General Notes')->first();
    expect($nb2)->not->toBeNull()
        ->and($nb2->area_id)->toBeNull();

    // 3. Rejects area of another project
    Livewire::actingAs($user)
        ->test('projects.notebooks.⚡create-notebook', ['project' => $projectA])
        ->set('form.name', 'Invalid Area Notes')
        ->set('form.description', 'Desc')
        ->set('form.areaId', $areaB->id)
        ->call('save')
        ->assertHasErrors(['form.areaId']);
});

test('notebook change area in show-notebooks accepts area of same project, accepts null, and rejects area of another project', function () {
    $user = User::factory()->create();
    $projectA = Project::factory()->create(['owner_id' => $user->id]);
    $projectB = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)->execute($projectA);
    app(CreateDefaultAreasAction::class)->execute($projectB);

    $areaA = $projectA->areas()->where('slug', 'design')->first();
    $areaB = $projectB->areas()->where('slug', 'design')->first();

    $notebook = Notebook::factory()->create([
        'project_id' => $projectA->id,
        'created_by' => $user->id,
        'name' => 'Design System',
        'area_id' => null,
    ]);

    $component = Livewire::actingAs($user)
        ->test('projects.notebooks.⚡show-notebooks', ['project' => $projectA]);

    // 1. Accepts area of same project
    $component->call('openChangeAreaModal', $notebook->id)
        ->set('notebookAreaId', $areaA->id)
        ->call('saveNotebookArea')
        ->assertHasNoErrors();

    $notebook->refresh();
    expect($notebook->area_id)->toBe($areaA->id);

    // 2. Accepts null
    $component->call('openChangeAreaModal', $notebook->id)
        ->set('notebookAreaId', null)
        ->call('saveNotebookArea')
        ->assertHasNoErrors();

    $notebook->refresh();
    expect($notebook->area_id)->toBeNull();

    // 3. Rejects area of another project
    $component->call('openChangeAreaModal', $notebook->id)
        ->set('notebookAreaId', $areaB->id)
        ->call('saveNotebookArea')
        ->assertHasErrors(['notebookAreaId']);
});

test('convert inbox to task accepts area of same project, accepts null, and rejects area of another project', function () {
    $user = User::factory()->create();
    $projectA = Project::factory()->create(['owner_id' => $user->id]);
    $projectB = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)->execute($projectA);
    app(CreateDefaultAreasAction::class)->execute($projectB);

    $areaA = $projectA->areas()->where('slug', 'backend')->first();
    $areaB = $projectB->areas()->where('slug', 'backend')->first();

    // 1. Accepts area of same project
    $item1 = InboxItem::factory()->create(['user_id' => $user->id, 'project_id' => $projectA->id, 'content' => 'Idea 1']);
    Livewire::actingAs($user)
        ->test('projects.inbox.⚡convert-to-task')
        ->call('loadModal', $item1->id)
        ->set('form.projectId', $projectA->id)
        ->set('form.areaId', $areaA->id)
        ->call('convert')
        ->assertHasNoErrors();

    $task1 = Task::where('title', 'Idea 1')->first();
    expect($task1)->not->toBeNull()
        ->and($task1->area_id)->toBe($areaA->id);

    // 2. Accepts null
    $item2 = InboxItem::factory()->create(['user_id' => $user->id, 'project_id' => $projectA->id, 'content' => 'Idea 2']);
    Livewire::actingAs($user)
        ->test('projects.inbox.⚡convert-to-task')
        ->call('loadModal', $item2->id)
        ->set('form.projectId', $projectA->id)
        ->set('form.areaId', null)
        ->call('convert')
        ->assertHasNoErrors();

    $task2 = Task::where('title', 'Idea 2')->first();
    expect($task2)->not->toBeNull()
        ->and($task2->area_id)->toBeNull();

    // 3. Rejects area of another project
    $item3 = InboxItem::factory()->create(['user_id' => $user->id, 'project_id' => $projectA->id, 'content' => 'Idea 3']);
    Livewire::actingAs($user)
        ->test('projects.inbox.⚡convert-to-task')
        ->call('loadModal', $item3->id)
        ->set('form.projectId', $projectA->id)
        ->set('form.areaId', $areaB->id)
        ->call('convert')
        ->assertHasErrors(['form.areaId']);
});

test('convert inbox to notebook accepts area of same project, accepts null, and rejects area of another project', function () {
    $user = User::factory()->create();
    $projectA = Project::factory()->create(['owner_id' => $user->id]);
    $projectB = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)->execute($projectA);
    app(CreateDefaultAreasAction::class)->execute($projectB);

    $areaA = $projectA->areas()->where('slug', 'frontend')->first();
    $areaB = $projectB->areas()->where('slug', 'frontend')->first();

    // 1. Accepts area of same project
    $item1 = InboxItem::factory()->create(['user_id' => $user->id, 'project_id' => $projectA->id, 'content' => 'Note 1']);
    Livewire::actingAs($user)
        ->test('projects.inbox.⚡convert-to-notebook')
        ->call('loadModal', $item1->id)
        ->set('form.projectId', $projectA->id)
        ->set('form.areaId', $areaA->id)
        ->call('convert')
        ->assertHasNoErrors();

    $nb1 = Notebook::where('description', 'Note 1')->first();
    expect($nb1)->not->toBeNull()
        ->and($nb1->area_id)->toBe($areaA->id);

    // 2. Accepts null
    $item2 = InboxItem::factory()->create(['user_id' => $user->id, 'project_id' => $projectA->id, 'content' => 'Note 2']);
    Livewire::actingAs($user)
        ->test('projects.inbox.⚡convert-to-notebook')
        ->call('loadModal', $item2->id)
        ->set('form.projectId', $projectA->id)
        ->set('form.areaId', null)
        ->call('convert')
        ->assertHasNoErrors();

    $nb2 = Notebook::where('description', 'Note 2')->first();
    expect($nb2)->not->toBeNull()
        ->and($nb2->area_id)->toBeNull();

    // 3. Rejects area of another project
    $item3 = InboxItem::factory()->create(['user_id' => $user->id, 'project_id' => $projectA->id, 'content' => 'Note 3']);
    Livewire::actingAs($user)
        ->test('projects.inbox.⚡convert-to-notebook')
        ->call('loadModal', $item3->id)
        ->set('form.projectId', $projectA->id)
        ->set('form.areaId', $areaB->id)
        ->call('convert')
        ->assertHasErrors(['form.areaId']);
});
