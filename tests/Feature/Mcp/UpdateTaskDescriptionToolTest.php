<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Servers\FunWorkServer;
use App\Mcp\Tools\UpdateTaskDescriptionTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

test('update_task_description updates the description for the project owner', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'description' => 'Original description',
        'status' => TaskStatus::Doing,
        'priority' => TaskPriority::High,
    ]);

    $newDescription = "# Technical Brief\n\n- Criterion 1\n- Criterion 2";

    FunWorkServer::actingAs($user)
        ->tool(UpdateTaskDescriptionTool::class, [
            'task_id' => $task->id,
            'description' => $newDescription,
        ])
        ->assertOk()
        ->assertSee($task->title)
        ->assertSee('Technical Brief')
        ->assertSee('Doing')
        ->assertSee('High');

    expect($task->fresh()->description)->toBe($newDescription);
});

test('update_task_description rejects unauthenticated requests', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'description' => 'Original description',
    ]);

    FunWorkServer::tool(UpdateTaskDescriptionTool::class, [
        'task_id' => $task->id,
        'description' => 'Updated without auth',
    ])
        ->assertHasErrors(['Unauthenticated.']);

    expect($task->fresh()->description)->toBe('Original description');
});

test('update_task_description returns Task not found when task does not exist', function () {
    $user = User::factory()->create();

    FunWorkServer::actingAs($user)
        ->tool(UpdateTaskDescriptionTool::class, [
            'task_id' => 999999,
            'description' => 'Updated description',
        ])
        ->assertHasErrors(['Task not found.']);
});

test('update_task_description rejects updating a task owned by another user and leaves description unchanged', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $project = Project::factory()->create(['owner_id' => $userA->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'description' => 'Original description',
    ]);

    FunWorkServer::actingAs($userB)
        ->tool(UpdateTaskDescriptionTool::class, [
            'task_id' => $task->id,
            'description' => 'Hacked description',
        ])
        ->assertHasErrors(['You are not authorized to update this task.']);

    expect($task->fresh()->description)->toBe('Original description');
});

test('update_task_description rejects a missing or empty description', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'description' => 'Original description',
    ]);

    FunWorkServer::actingAs($user)
        ->tool(UpdateTaskDescriptionTool::class, [
            'task_id' => $task->id,
        ])
        ->assertHasErrors();

    FunWorkServer::actingAs($user)
        ->tool(UpdateTaskDescriptionTool::class, [
            'task_id' => $task->id,
            'description' => '',
        ])
        ->assertHasErrors();

    expect($task->fresh()->description)->toBe('Original description');
});

test('update_task_description rejects a description over the max length', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'description' => 'Original description',
    ]);

    FunWorkServer::actingAs($user)
        ->tool(UpdateTaskDescriptionTool::class, [
            'task_id' => $task->id,
            'description' => str_repeat('a', Task::MAX_DESCRIPTION_LENGTH + 1),
        ])
        ->assertHasErrors();

    expect($task->fresh()->description)->toBe('Original description');
});
