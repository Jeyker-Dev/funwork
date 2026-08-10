<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

test('users have a default timezone of UTC', function () {
    $user = User::factory()->create();
    expect($user->timezone)->toBe('UTC');
});

test('task due_date is cast to datetime', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $date = Carbon::parse('2026-10-31 15:30:00');

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'due_date' => $date,
    ]);

    // Retrieve fresh from DB
    $freshTask = Task::find($task->id);

    expect($freshTask->due_date)->toBeInstanceOf(CarbonInterface::class);
    expect($freshTask->due_date->format('Y-m-d H:i:s'))->toBe('2026-10-31 15:30:00');
});
