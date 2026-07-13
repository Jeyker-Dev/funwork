<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('create task component renders successfully', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    $response = Livewire::test('projects.tasks.⚡create-task', ['project' => $project, 'status' => 'Todo']);

    $response->assertSee('Create Task');
});

test('it validates required fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    $response = Livewire::test('projects.tasks.⚡create-task', ['project' => $project, 'status' => 'Todo'])
        ->set('form.title', '')
        ->set('form.description', '')
        ->call('save');

    $response->assertHasErrors(['form.title', 'form.description']);
});

test('it creates a task with multiple assigned users and calculates position', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $assignees = User::factory()->count(2)->create();

    // Create an existing task to test position calculation
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => 'Todo',
        'position' => 1,
    ]);

    $this->actingAs($user);

    $response = Livewire::test('projects.tasks.⚡create-task', ['project' => $project, 'status' => 'Todo'])
        ->set('form.title', 'New Test Task')
        ->set('form.description', 'This is a description')
        ->set('form.priority', 'High')
        ->set('form.dueDate', '2026-10-10')
        ->set('form.assignedUserIds', $assignees->pluck('id')->toArray())
        ->call('save');

    $response->assertHasNoErrors();
    $response->assertDispatched('task-created');

    $this->assertDatabaseHas('tasks', [
        'title' => 'New Test Task',
        'project_id' => $project->id,
        'status' => 'Todo',
        'position' => 2, // Max was 1, so new should be 2
    ]);

    $task = Task::where('title', 'New Test Task')->first();
    expect($task->users)->toHaveCount(2);
});
