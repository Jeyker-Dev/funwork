<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('task-board renders neutral columns with TaskStatus dots only in column headers', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    foreach (TaskStatus::cases() as $status) {
        Task::factory()->count(4)->create([
            'project_id' => $project->id,
            'status' => $status->value,
            'priority' => TaskPriority::Medium,
        ]);
    }

    Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-board', ['project' => $project])
        ->assertOk()
        ->assertSee('bg-surface-low')
        ->assertSee(TaskStatus::Todo->dot())
        ->assertSee(TaskStatus::Doing->dot())
        ->assertSee(TaskStatus::Done->dot())
        ->assertDontSee('bg-[rgba(255,128,64,0.16)]')
        ->assertDontSee('bg-[rgba(255,220,70,0.17)]')
        ->assertDontSee('bg-[rgba(38,186,112,0.13)]');
});

test('create-task renders quiet add task trigger and preselects status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('projects.tasks.⚡create-task', ['project' => $project, 'status' => 'Todo'])
        ->assertOk()
        ->assertSee('Add task')
        ->assertSet('form.status', 'Todo');

    Livewire::actingAs($user)
        ->test('projects.tasks.⚡create-task', ['project' => $project, 'status' => 'Doing'])
        ->assertOk()
        ->assertSee('Add task')
        ->assertSet('form.status', 'Doing');

    Livewire::actingAs($user)
        ->test('projects.tasks.⚡create-task', ['project' => $project, 'status' => 'Done'])
        ->assertOk()
        ->assertSee('Add task')
        ->assertSet('form.status', 'Done');
});

test('task-more-indicator renders current status color classes for each status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-more-indicator', ['project' => $project, 'status' => 'Todo'])
        ->assertOk()
        ->assertSee('border-orange-200 bg-orange-50 hover:bg-orange-200 text-orange-500');

    Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-more-indicator', ['project' => $project, 'status' => 'Doing'])
        ->assertOk()
        ->assertSee('border-yellow-200 bg-yellow-50 hover:bg-yellow-200 text-yellow-600');

    Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-more-indicator', ['project' => $project, 'status' => 'Done'])
        ->assertOk()
        ->assertSee('border-green-200 bg-green-50 hover:bg-green-200 text-green-600');
});

test('slider-tasks renders current status color classes for each status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Todo Task',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Low,
        'position' => 1,
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Doing Task',
        'status' => TaskStatus::Doing,
        'priority' => TaskPriority::Medium,
        'position' => 2,
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Done Task',
        'status' => TaskStatus::Done,
        'priority' => TaskPriority::High,
        'position' => 3,
    ]);

    $test = Livewire::actingAs($user)
        ->test('projects.tasks.⚡slider-tasks', ['project' => $project])
        ->assertOk()
        ->assertSee('bg-orange-400')
        ->assertSee('bg-yellow-400')
        ->assertSee('bg-green-400');
});
