<?php

use App\Actions\Notifications\BuildDailyDigestAction;
use App\Enums\TaskStatus;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

it('returns an empty digest when the user has no active tasks or inbox items', function () {
    $user = User::factory()->create();
    Project::factory()->create(['owner_id' => $user->id]);

    $digest = app(BuildDailyDigestAction::class)($user);

    expect($digest->isEmpty())->toBeTrue()
        ->and($digest->overdueTasks)->toBeEmpty()
        ->and($digest->todayTasks)->toBeEmpty()
        ->and($digest->upcomingTasks)->toBeEmpty()
        ->and($digest->inboxItems)->toBeEmpty()
        ->and($digest->summaryText())->toBe('Nothing pending for today.');
});

it('collects overdue open tasks strictly before the user local today', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00', 'UTC')); // 2026-11-03 in America/New_York

    $user = User::factory()->create(['timezone' => 'America/New_York']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $overdue = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-02 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'due_date' => '2026-11-03 00:00:00',
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);

    expect($digest->date)->toBe('2026-11-03')
        ->and($digest->overdueTasks->pluck('id')->all())->toBe([$overdue->id]);
});

it('collects open tasks due today in the user local timezone', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00', 'UTC')); // 2026-11-03 in America/New_York

    $user = User::factory()->create(['timezone' => 'America/New_York']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $today = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'due_date' => '2026-11-03 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-04 00:00:00',
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);

    expect($digest->todayTasks->pluck('id')->all())->toBe([$today->id]);
});

it('collects open tasks due in the next 3 days and excludes day 4 or undated tasks', function () {
    $this->travelTo(Carbon::parse('2026-11-03 12:00:00', 'America/New_York'));

    $user = User::factory()->create(['timezone' => 'America/New_York']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $dayOne = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-04 00:00:00',
    ]);

    $dayThree = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-06 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-07 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => null,
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);

    expect($digest->upcomingTasks->pluck('id')->all())->toBe([$dayOne->id, $dayThree->id]);
});

it('collects unprocessed inbox items across active owned projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $inboxItem = InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);

    expect($digest->isEmpty())->toBeFalse()
        ->and($digest->inboxItems->pluck('id')->all())->toBe([$inboxItem->id]);
});

it('formats human-readable summary text for non-empty digest counts', function () {
    $this->travelTo(Carbon::parse('2026-11-03 12:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-02 00:00:00',
    ]);

    Task::factory()->count(2)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-05 00:00:00',
    ]);

    InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);

    expect($digest->summaryText())->toBe('You have 1 overdue task, 2 upcoming tasks and 1 inbox item.');
});

it('excludes done tasks from the digest', function () {
    $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $activeProject = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $activeProject->id,
        'status' => TaskStatus::Done,
        'due_date' => '2026-11-10 00:00:00',
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);

    expect($digest->isEmpty())->toBeTrue();
});

it('excludes tasks and inbox items belonging to archived projects', function () {
    $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $archivedProject = Project::factory()->create([
        'owner_id' => $user->id,
        'archived_at' => now()->subDay(),
    ]);

    Task::factory()->create([
        'project_id' => $archivedProject->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-10 00:00:00',
    ]);
    InboxItem::factory()->create([
        'project_id' => $archivedProject->id,
        'user_id' => $user->id,
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);

    expect($digest->isEmpty())->toBeTrue();
});

it('excludes tasks and inbox items belonging to other users', function () {
    $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $otherUser = User::factory()->create(['timezone' => 'UTC']);
    $otherProject = Project::factory()->create(['owner_id' => $otherUser->id]);

    Task::factory()->create([
        'project_id' => $otherProject->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-10 00:00:00',
    ]);
    InboxItem::factory()->create([
        'project_id' => $otherProject->id,
        'user_id' => $otherUser->id,
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);

    expect($digest->isEmpty())->toBeTrue();
});

it('handles daylight saving time transitions cleanly when computing the 3-day window', function () {
    // 2026-11-01 is DST fallback in America/New_York (25-hour day)
    $this->travelTo(Carbon::parse('2026-11-01 12:00:00', 'America/New_York'));

    $user = User::factory()->create(['timezone' => 'America/New_York']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $dayThree = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-04 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-05 00:00:00',
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);

    expect($digest->date)->toBe('2026-11-01')
        ->and($digest->upcomingTasks->pluck('id')->all())->toBe([$dayThree->id]);
});
