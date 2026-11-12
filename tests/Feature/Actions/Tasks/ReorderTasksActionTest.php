<?php

use App\Actions\Tasks\ReorderTasksAction;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

test('it reorders tasks and updates status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $task1 = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'position' => 1]);
    $task2 = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'position' => 2]);
    $task3 = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Doing, 'position' => 1]);

    $this->actingAs($user);

    $action = new ReorderTasksAction;
    $payload = [
        'Todo' => [$task2->id],
        'Doing' => [$task1->id, $task3->id],
    ];

    $action($payload, $project->id);

    expect($task1->refresh()->status)->toBe(TaskStatus::Doing);
    expect($task1->refresh()->position)->toBe(1);

    expect($task3->refresh()->status)->toBe(TaskStatus::Doing);
    expect($task3->refresh()->position)->toBe(2);

    expect($task2->refresh()->status)->toBe(TaskStatus::Todo);
    expect($task2->refresh()->position)->toBe(1);
});

test('it rejects invalid status keys before modifying any tasks', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $task1 = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'position' => 1]);
    $task2 = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Doing, 'position' => 2]);

    $this->actingAs($user);

    $action = new ReorderTasksAction;
    $payload = [
        'Doing' => [$task1->id],
        'InvalidStatus' => [$task2->id],
    ];

    expect(fn () => $action($payload, $project->id))
        ->toThrow(ValidationException::class);

    expect($task1->refresh()->status)->toBe(TaskStatus::Todo)
        ->and($task1->refresh()->position)->toBe(1)
        ->and($task2->refresh()->status)->toBe(TaskStatus::Doing)
        ->and($task2->refresh()->position)->toBe(2);
});

test('it prevents unauthorized users from reordering tasks', function () {
    $owner = User::factory()->create();
    $unauthorizedUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $task = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'position' => 1]);

    $this->actingAs($unauthorizedUser);

    $action = new ReorderTasksAction;
    $payload = [
        'Doing' => [$task->id],
    ];

    $action($payload, $project->id);
})->throws(AuthorizationException::class);
