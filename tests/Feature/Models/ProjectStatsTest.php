<?php

use App\Enums\TaskStatus;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('dashboardStats counts open tasks, today tasks in user timezone, and inbox items via relation', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00'));

    $user = User::factory()->create([
        'timezone' => 'America/New_York',
    ]);
    $project = Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    // Open task due today in America/New_York (2026-11-03)
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-03 00:00:00',
    ]);

    // Open task due on another day
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'due_date' => '2026-11-01 00:00:00',
    ]);

    // Open task without due date
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => null,
    ]);

    // Done task due today - must be excluded from open and today counts
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'due_date' => '2026-11-03 00:00:00',
    ]);

    // Active inbox items
    InboxItem::factory()->count(2)->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
    ]);

    // Soft-deleted inbox item - must be excluded
    $deletedItem = InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
    ]);
    $deletedItem->delete();

    $stats = $project->dashboardStats($user);

    expect($stats)->toBe([
        'tasks_count' => 3,
        'today_tasks_count' => 1,
        'inbox_count' => 2,
    ]);
});

test('dashboardStats respects UTC when user has UTC timezone', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00'));

    $user = User::factory()->create([
        'timezone' => 'UTC',
    ]);
    $project = Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    // In UTC, current day is 2026-11-04
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-04 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-03 00:00:00',
    ]);

    $stats = $project->dashboardStats($user);

    expect($stats['today_tasks_count'])->toBe(1)
        ->and($stats['tasks_count'])->toBe(2);
});
