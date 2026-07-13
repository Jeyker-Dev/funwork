<?php

use App\Livewire\Actions\ReorderTasks;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('it reorders tasks and updates status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $task1 = Task::factory()->create(['project_id' => $project->id, 'status' => 'Todo', 'position' => 1]);
    $task2 = Task::factory()->create(['project_id' => $project->id, 'status' => 'Todo', 'position' => 2]);
    $task3 = Task::factory()->create(['project_id' => $project->id, 'status' => 'Doing', 'position' => 1]);

    $this->actingAs($user);

    $action = new ReorderTasks;
    $payload = [
        'Todo' => [$task2->id],
        'Doing' => [$task1->id, $task3->id],
    ];

    $action($payload, $project->id);

    expect($task1->refresh()->status)->toBe('Doing');
    expect($task1->refresh()->position)->toBe(1);

    expect($task3->refresh()->status)->toBe('Doing');
    expect($task3->refresh()->position)->toBe(2);

    expect($task2->refresh()->status)->toBe('Todo');
    expect($task2->refresh()->position)->toBe(1);
});

test('it prevents unauthorized users from reordering tasks', function () {
    $owner = User::factory()->create();
    $unauthorizedUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'Todo', 'position' => 1]);

    $this->actingAs($unauthorizedUser);

    $action = new ReorderTasks;
    $payload = [
        'Doing' => [$task->id],
    ];

    $action($payload, $project->id);
})->throws(HttpException::class, 'Unauthorized action.');
