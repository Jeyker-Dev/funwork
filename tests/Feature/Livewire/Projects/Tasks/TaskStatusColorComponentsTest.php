<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('task-board renders current status color classes without changes', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    // Create 4 tasks per status so hiddenCount > 0 and the more indicator is rendered for each status
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
        // Column dots & counts
        ->assertSee('bg-orange-500')
        ->assertSee('bg-yellow-500')
        ->assertSee('bg-green-500')
        // Column borders
        ->assertSee('border-[rgba(255,128,64,0.48)]')
        ->assertSee('border-[rgba(255,220,70,0.38)]')
        ->assertSee('border-[rgba(38,186,112,0.33)]')
        // Column backgrounds
        ->assertSee('bg-[rgba(255,128,64,0.16)]')
        ->assertSee('bg-[rgba(255,220,70,0.17)]')
        ->assertSee('bg-[rgba(38,186,112,0.13)]')
        // More indicators (badges)
        ->assertSee('border-orange-200 bg-orange-50 hover:bg-orange-200 text-orange-500')
        ->assertSee('border-yellow-200 bg-yellow-50 hover:bg-yellow-200 text-yellow-600')
        ->assertSee('border-green-200 bg-green-50 hover:bg-green-200 text-green-600');
});

test('create-task renders current status color classes for each status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('projects.tasks.⚡create-task', ['project' => $project, 'status' => 'Todo'])
        ->assertOk()
        ->assertSee('border-[rgba(255,128,64,0.48)] bg-[rgba(255,128,64,0.23)] hover:bg-[rgba(255,128,64,0.33)]')
        ->assertSee('text-orange-500');

    Livewire::actingAs($user)
        ->test('projects.tasks.⚡create-task', ['project' => $project, 'status' => 'Doing'])
        ->assertOk()
        ->assertSee('border-[rgba(255,220,70,0.38)] bg-[rgba(255,220,70,0.17)] hover:bg-[rgba(255,220,70,0.27)]')
        ->assertSee('text-yellow-500');

    Livewire::actingAs($user)
        ->test('projects.tasks.⚡create-task', ['project' => $project, 'status' => 'Done'])
        ->assertOk()
        ->assertSee('border-[rgba(38,186,112,0.33)] bg-[rgba(38,186,112,0.13)] hover:bg-[rgba(38,186,112,0.23)]')
        ->assertSee('text-green-500');
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
