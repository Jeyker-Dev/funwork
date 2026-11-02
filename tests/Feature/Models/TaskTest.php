<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('it can be created using factory', function () {
    $task = Task::factory()->create();

    expect($task)->toBeInstanceOf(Task::class)
        ->and($task->title)->not->toBeEmpty()
        ->and($task->status)->toBeInstanceOf(TaskStatus::class)
        ->and($task->priority)->toBeInstanceOf(TaskPriority::class);
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

test('normalization migration converts lowercase status and priority values to enum values', function () {
    $project = Project::factory()->create();

    $todoLowId = DB::table('tasks')->insertGetId([
        'title' => 'Legacy Todo Low',
        'description' => 'Legacy task',
        'status' => 'todo',
        'priority' => 'low',
        'project_id' => $project->id,
        'position' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $doingMediumId = DB::table('tasks')->insertGetId([
        'title' => 'Legacy Doing Medium',
        'description' => 'Legacy task',
        'status' => 'doing',
        'priority' => 'medium',
        'project_id' => $project->id,
        'position' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $doneHighId = DB::table('tasks')->insertGetId([
        'title' => 'Legacy Done High',
        'description' => 'Legacy task',
        'status' => 'done',
        'priority' => 'high',
        'project_id' => $project->id,
        'position' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_11_02_151329_normalize_task_status_and_priority_values.php');
    $migration->up();

    expect(DB::table('tasks')->where('id', $todoLowId)->first())
        ->status->toBe('Todo')
        ->priority->toBe('Low');

    expect(DB::table('tasks')->where('id', $doingMediumId)->first())
        ->status->toBe('Doing')
        ->priority->toBe('Medium');

    expect(DB::table('tasks')->where('id', $doneHighId)->first())
        ->status->toBe('Done')
        ->priority->toBe('High');

    $migration->down();

    expect(DB::table('tasks')->where('id', $todoLowId)->first())
        ->status->toBe('Todo')
        ->priority->toBe('Low');
});
