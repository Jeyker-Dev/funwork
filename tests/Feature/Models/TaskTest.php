<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

test('it can be created using factory', function () {
    $task = Task::factory()->create();

    expect($task)->toBeInstanceOf(Task::class);
    expect($task->title)->not->toBeEmpty();
});

test('it belongs to a project', function () {
    $task = Task::factory()->create();

    expect($task->project)->toBeInstanceOf(Project::class);
});

test('it can have multiple assigned users', function () {
    $task = Task::factory()->create();
    $users = User::factory()->count(2)->create();

    $task->users()->attach($users);

    expect($task->users)->toHaveCount(2);
    expect($task->users->first())->toBeInstanceOf(User::class);
});
