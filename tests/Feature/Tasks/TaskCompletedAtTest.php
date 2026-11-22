<?php

use App\Actions\Tasks\MoveTaskAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Servers\FunWorkServer;
use App\Mcp\Tools\UpdateTaskStatusTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('adds completed_at column and backfills done tasks from updated_at', function () {
    expect(Schema::hasColumn('tasks', 'completed_at'))->toBeTrue();

    $project = Project::factory()->create();
    $doneUpdatedAt = Carbon::parse('2026-11-10 14:30:00');
    $todoUpdatedAt = Carbon::parse('2026-11-11 09:00:00');

    $doneId = DB::table('tasks')->insertGetId([
        'title' => 'Already Done',
        'description' => 'Done before migration',
        'status' => TaskStatus::Done->value,
        'priority' => TaskPriority::Medium->value,
        'project_id' => $project->id,
        'position' => 1,
        'completed_at' => null,
        'created_at' => $doneUpdatedAt->copy()->subDay(),
        'updated_at' => $doneUpdatedAt,
    ]);

    $todoId = DB::table('tasks')->insertGetId([
        'title' => 'Still Todo',
        'description' => 'Open task',
        'status' => TaskStatus::Todo->value,
        'priority' => TaskPriority::Medium->value,
        'project_id' => $project->id,
        'position' => 2,
        'completed_at' => null,
        'created_at' => $todoUpdatedAt->copy()->subDay(),
        'updated_at' => $todoUpdatedAt,
    ]);

    $migrationFiles = glob(database_path('migrations/*_add_completed_at_to_tasks_table.php'));
    expect($migrationFiles)->toHaveCount(1);

    $migration = require $migrationFiles[0];
    $migration->down();
    expect(Schema::hasColumn('tasks', 'completed_at'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('tasks', 'completed_at'))->toBeTrue();

    $doneRow = DB::table('tasks')->where('id', $doneId)->first();
    $todoRow = DB::table('tasks')->where('id', $todoId)->first();

    expect($doneRow->completed_at)->toBe($doneUpdatedAt->toDateTimeString())
        ->and($todoRow->completed_at)->toBeNull();
});

it('sets completed_at when a task is created in done or moved to done and clears it when leaving done', function () {
    Carbon::setTestNow('2026-11-22 15:00:00');

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $createdDone = Task::create([
        'title' => 'Created Done',
        'description' => 'Already finished',
        'status' => TaskStatus::Done,
        'priority' => TaskPriority::Low,
        'project_id' => $project->id,
        'position' => 1,
    ]);

    expect($createdDone->completed_at)->not->toBeNull()
        ->and($createdDone->completed_at->equalTo(now()))->toBeTrue();

    $task = Task::create([
        'title' => 'Work item',
        'description' => 'Needs doing',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Medium,
        'project_id' => $project->id,
        'position' => 2,
    ]);

    expect($task->completed_at)->toBeNull();

    Carbon::setTestNow('2026-11-22 16:00:00');
    $task->changeStatus(TaskStatus::Done);

    expect($task->fresh()->completed_at?->toDateTimeString())->toBe('2026-11-22 16:00:00');

    Carbon::setTestNow('2026-11-22 17:00:00');
    $task->update(['title' => 'Work item renamed']);

    expect($task->fresh()->completed_at?->toDateTimeString())->toBe('2026-11-22 16:00:00');

    $task->changeStatus(TaskStatus::Doing);

    expect($task->fresh()->completed_at)->toBeNull();

    Carbon::setTestNow();
});

it('sets and clears completed_at via board drag, detail panel, and mcp tool', function () {
    Carbon::setTestNow('2026-11-22 18:00:00');

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'position' => 1,
    ]);

    $this->actingAs($user);

    // Board drag into Done
    app(MoveTaskAction::class)($task, TaskStatus::Done, 0);
    expect($task->fresh()->completed_at?->toDateTimeString())->toBe('2026-11-22 18:00:00');

    // Detail panel moving back to Todo
    Livewire::actingAs($user)
        ->test('projects.tasks.task-detail-panel', [
            'project' => $project,
            'taskId' => $task->id,
        ])
        ->set('form.status', TaskStatus::Todo->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($task->fresh()->completed_at)->toBeNull();

    // MCP update_task_status to Done
    Carbon::setTestNow('2026-11-22 19:00:00');
    FunWorkServer::actingAs($user)
        ->tool(UpdateTaskStatusTool::class, [
            'task_id' => $task->id,
            'status' => TaskStatus::Done->value,
        ])
        ->assertOk();

    expect($task->fresh()->completed_at?->toDateTimeString())->toBe('2026-11-22 19:00:00');

    // MCP update_task_status back to Doing
    FunWorkServer::actingAs($user)
        ->tool(UpdateTaskStatusTool::class, [
            'task_id' => $task->id,
            'status' => TaskStatus::Doing->value,
        ])
        ->assertOk();

    expect($task->fresh()->completed_at)->toBeNull();

    Carbon::setTestNow();
});
