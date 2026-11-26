<?php

use App\Actions\Tasks\ReorderTasksAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('adds status_changed_at column and backfills existing tasks from updated_at', function () {
    expect(Schema::hasColumn('tasks', 'status_changed_at'))->toBeTrue();

    $project = Project::factory()->create();
    $todoUpdatedAt = Carbon::parse('2026-11-10 14:30:00');
    $doingUpdatedAt = Carbon::parse('2026-11-11 09:00:00');
    $doneUpdatedAt = Carbon::parse('2026-11-12 16:45:00');

    $todoId = DB::table('tasks')->insertGetId([
        'title' => 'Legacy Todo',
        'description' => 'Todo before migration',
        'status' => TaskStatus::Todo->value,
        'priority' => TaskPriority::Medium->value,
        'project_id' => $project->id,
        'position' => 1,
        'status_changed_at' => null,
        'created_at' => $todoUpdatedAt->copy()->subDay(),
        'updated_at' => $todoUpdatedAt,
    ]);

    $doingId = DB::table('tasks')->insertGetId([
        'title' => 'Legacy Doing',
        'description' => 'Doing before migration',
        'status' => TaskStatus::Doing->value,
        'priority' => TaskPriority::Medium->value,
        'project_id' => $project->id,
        'position' => 2,
        'status_changed_at' => null,
        'created_at' => $doingUpdatedAt->copy()->subDay(),
        'updated_at' => $doingUpdatedAt,
    ]);

    $doneId = DB::table('tasks')->insertGetId([
        'title' => 'Legacy Done',
        'description' => 'Done before migration',
        'status' => TaskStatus::Done->value,
        'priority' => TaskPriority::Medium->value,
        'project_id' => $project->id,
        'position' => 3,
        'status_changed_at' => null,
        'created_at' => $doneUpdatedAt->copy()->subDay(),
        'updated_at' => $doneUpdatedAt,
    ]);

    $migrationFiles = glob(database_path('migrations/*_add_status_changed_at_to_tasks_table.php'));
    expect($migrationFiles)->toHaveCount(1);

    $migration = require $migrationFiles[0];
    $migration->down();
    expect(Schema::hasColumn('tasks', 'status_changed_at'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('tasks', 'status_changed_at'))->toBeTrue();

    expect(DB::table('tasks')->where('id', $todoId)->value('status_changed_at'))->toBe($todoUpdatedAt->toDateTimeString())
        ->and(DB::table('tasks')->where('id', $doingId)->value('status_changed_at'))->toBe($doingUpdatedAt->toDateTimeString())
        ->and(DB::table('tasks')->where('id', $doneId)->value('status_changed_at'))->toBe($doneUpdatedAt->toDateTimeString());
});

it('sets status_changed_at on create and updates it only when status changes', function () {
    Carbon::setTestNow('2026-11-22 10:00:00');

    $project = Project::factory()->create();

    $task = Task::create([
        'title' => 'Build health check',
        'description' => 'Implement action',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Medium,
        'project_id' => $project->id,
        'position' => 1,
    ]);

    expect($task->status_changed_at?->toDateTimeString())->toBe('2026-11-22 10:00:00');

    Carbon::setTestNow('2026-11-22 12:00:00');
    $task->update(['title' => 'Build health check action']);

    expect($task->fresh()->status_changed_at?->toDateTimeString())->toBe('2026-11-22 10:00:00');

    Carbon::setTestNow('2026-11-22 14:00:00');
    $task->changeStatus(TaskStatus::Doing);

    expect($task->fresh()->status_changed_at?->toDateTimeString())->toBe('2026-11-22 14:00:00');

    Carbon::setTestNow('2026-11-22 16:00:00');
    $task->changeStatus(TaskStatus::Done);

    $reloaded = $task->fresh();
    expect($reloaded->status_changed_at?->toDateTimeString())->toBe('2026-11-22 16:00:00')
        ->and($reloaded->completed_at?->toDateTimeString())->toBe('2026-11-22 16:00:00');

    Carbon::setTestNow();
});

it('does not change status_changed_at when reordering within the same status', function () {
    Carbon::setTestNow('2026-11-10 09:00:00');

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $firstDoing = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'position' => 1,
    ]);

    $secondDoing = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'position' => 2,
    ]);

    $this->actingAs($user);

    Carbon::setTestNow('2026-11-26 15:00:00');

    app(ReorderTasksAction::class)([
        TaskStatus::Doing->value => [$secondDoing->id, $firstDoing->id],
    ], $project->id);

    $firstReloaded = $firstDoing->fresh();
    $secondReloaded = $secondDoing->fresh();

    expect($secondReloaded->position)->toBe(1)
        ->and($firstReloaded->position)->toBe(2)
        ->and($firstReloaded->updated_at?->toDateTimeString())->toBe('2026-11-26 15:00:00')
        ->and($secondReloaded->updated_at?->toDateTimeString())->toBe('2026-11-26 15:00:00')
        ->and($firstReloaded->status_changed_at?->toDateTimeString())->toBe('2026-11-10 09:00:00')
        ->and($secondReloaded->status_changed_at?->toDateTimeString())->toBe('2026-11-10 09:00:00');

    Carbon::setTestNow('2026-11-26 16:00:00');

    app(ReorderTasksAction::class)([
        TaskStatus::Todo->value => [$firstDoing->id],
        TaskStatus::Doing->value => [$secondDoing->id],
    ], $project->id);

    expect($firstDoing->fresh()->status_changed_at?->toDateTimeString())->toBe('2026-11-26 16:00:00')
        ->and($secondDoing->fresh()->status_changed_at?->toDateTimeString())->toBe('2026-11-10 09:00:00');

    Carbon::setTestNow();
});
