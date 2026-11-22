<?php

use App\Actions\Projects\GetProjectsOverviewAction;
use App\Enums\TaskStatus;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('greeting uses the user first name and time of day in their timezone', function (string $utcTime, string $expectedGreeting) {
    $this->travelTo(Carbon::parse($utcTime));

    $user = User::factory()->create([
        'name' => 'Elena Vance',
        'username' => 'evance',
        'timezone' => 'America/New_York',
    ]);

    $overview = app(GetProjectsOverviewAction::class)($user);

    expect($overview->greeting)->toBe("{$expectedGreeting}, Elena");
})->with([
    'morning in New York' => ['2026-11-23 14:00:00', 'Good morning'],
    'afternoon in New York' => ['2026-11-23 19:00:00', 'Good afternoon'],
    'evening in New York' => ['2026-11-24 01:00:00', 'Good evening'],
]);

test('summary counts active projects and non-done tasks due within the current week', function () {
    // Wednesday 2026-11-25 12:00 UTC -> week is Mon 2026-11-23 to Sun 2026-11-29
    $this->travelTo(Carbon::parse('2026-11-25 12:00:00'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $otherUser = User::factory()->create(['timezone' => 'UTC']);

    $activeProject = Project::factory()->create(['owner_id' => $user->id, 'status' => 'active']);
    $planningProject = Project::factory()->create(['owner_id' => $user->id, 'status' => 'planning']);
    $archivedActiveProject = Project::factory()->create([
        'owner_id' => $user->id,
        'status' => 'active',
        'archived_at' => now()->subDay(),
    ]);
    $foreignProject = Project::factory()->create(['owner_id' => $otherUser->id, 'status' => 'active']);

    // Due this week, not Done -> counted (2)
    Task::factory()->create([
        'project_id' => $activeProject->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-23 00:00:00',
    ]);
    Task::factory()->create([
        'project_id' => $planningProject->id,
        'status' => TaskStatus::Doing,
        'due_date' => '2026-11-29 00:00:00',
    ]);

    // Excluded: Done task due this week
    Task::factory()->create([
        'project_id' => $activeProject->id,
        'status' => TaskStatus::Done,
        'due_date' => '2026-11-25 00:00:00',
    ]);

    // Excluded: task due next week, task due last week, undated task
    Task::factory()->create([
        'project_id' => $activeProject->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-30 00:00:00',
    ]);
    Task::factory()->create([
        'project_id' => $activeProject->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-22 00:00:00',
    ]);
    Task::factory()->create([
        'project_id' => $activeProject->id,
        'status' => TaskStatus::Todo,
        'due_date' => null,
    ]);

    // Excluded: archived project and foreign project tasks
    Task::factory()->create([
        'project_id' => $archivedActiveProject->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-25 00:00:00',
    ]);
    Task::factory()->create([
        'project_id' => $foreignProject->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-25 00:00:00',
    ]);

    $overview = app(GetProjectsOverviewAction::class)($user);

    expect($overview->activeProjectsCount)->toBe(1)
        ->and($overview->tasksDueThisWeekCount)->toBe(2);
});

test('most recently active project considers project, tasks, notebooks, and files updated_at', function () {
    $this->travelTo(Carbon::parse('2026-11-25 12:00:00'));

    $user = User::factory()->create();

    $olderProject = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Older Project',
        'updated_at' => now()->subDays(5),
    ]);
    $fileProject = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'File Touched Project',
        'updated_at' => now()->subDays(4),
    ]);
    $notebookProject = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Notebook Touched Project',
        'updated_at' => now()->subDays(3),
    ]);

    Task::factory()->create([
        'project_id' => $olderProject->id,
        'updated_at' => now()->subDays(2),
    ]);
    Notebook::factory()->create([
        'project_id' => $notebookProject->id,
        'created_by' => $user->id,
        'updated_at' => now()->subHour(),
    ]);
    ProjectFile::factory()->create([
        'project_id' => $fileProject->id,
        'created_by' => $user->id,
        'updated_at' => now()->subMinutes(10),
    ]);

    $overview = app(GetProjectsOverviewAction::class)($user);

    expect($overview->continueProject)->not->toBeNull()
        ->and($overview->continueProject->project->is($fileProject))->toBeTrue();
});

test('next task prefers earliest due open task over undated or later tasks', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    // Done task with earliest due date must be ignored
    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Completed Early Task',
        'status' => TaskStatus::Done,
        'due_date' => '2026-11-20 00:00:00',
    ]);

    // Undated task created earlier must lose to dated task
    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Undated Task',
        'status' => TaskStatus::Todo,
        'due_date' => null,
        'created_at' => now()->subDays(3),
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Later Due Task',
        'status' => TaskStatus::Doing,
        'due_date' => '2026-12-05 00:00:00',
    ]);

    $earliestOpenTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Earliest Open Task',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-28 00:00:00',
    ]);

    $overview = app(GetProjectsOverviewAction::class)($user);

    expect($overview->continueProject?->nextTask?->is($earliestOpenTask))->toBeTrue();
});

test('next task falls back to oldest Todo or Doing task when no open tasks have a due date', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Older Done Task',
        'status' => TaskStatus::Done,
        'due_date' => null,
        'created_at' => now()->subDays(5),
    ]);

    $oldestOpenTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Oldest Open Task',
        'status' => TaskStatus::Doing,
        'due_date' => null,
        'created_at' => now()->subDays(2),
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Newer Open Task',
        'status' => TaskStatus::Todo,
        'due_date' => null,
        'created_at' => now()->subDay(),
    ]);

    $overview = app(GetProjectsOverviewAction::class)($user);

    expect($overview->continueProject?->nextTask?->is($oldestOpenTask))->toBeTrue();
});

test('computes projects overview in constant number of queries regardless of project count', function () {
    $user = User::factory()->create();

    Project::factory()->count(8)->create(['owner_id' => $user->id])->each(function (Project $project) use ($user): void {
        Task::factory()->count(3)->create(['project_id' => $project->id]);
        Notebook::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    });

    DB::enableQueryLog();
    DB::flushQueryLog();

    app(GetProjectsOverviewAction::class)($user);

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(4);
});
