<?php

use App\Actions\Projects\GetProjectHealthAction;
use App\Enums\HealthCheckSeverity;
use App\Enums\TaskStatus;
use App\Models\Decision;
use App\Models\InboxItem;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('returns empty health checks for a healthy project and ignores overdue tasks', function () {
    $this->travelTo(Carbon::parse('2026-11-26 12:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    // Overdue tasks are shown in Today, not Project Health
    Task::factory()->count(5)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-20 00:00:00',
    ]);

    RepositoryConnection::factory()->active()->create(['project_id' => $project->id]);
    Decision::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'content_changed_at' => now(),
    ]);

    $health = app(GetProjectHealthAction::class)($project, $user);

    expect($health->isHealthy())->toBeTrue()
        ->and($health->count())->toBe(0)
        ->and($health->checks)->toBeEmpty();
});

test('stuck rule uses 7-day boundary in user timezone, counts only Doing tasks, and uses status_changed_at', function () {
    // 2026-11-26 02:00:00 UTC is 2026-11-25 21:00:00 in America/New_York
    $this->travelTo(Carbon::parse('2026-11-26 02:00:00', 'UTC'));

    $nyUser = User::factory()->create(['timezone' => 'America/New_York']);
    $utcUser = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $nyUser->id]);

    // 2026-11-18 23:00:00 UTC == 2026-11-18 18:00:00 NY
    // For NY (today = Nov 25, cutoff = Nov 18 00:00 NY = Nov 18 05:00 UTC): NOT stuck (just under)
    // For UTC (today = Nov 26, cutoff = Nov 19 00:00 UTC): STUCK (just over)
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'status_changed_at' => Carbon::parse('2026-11-18 23:00:00', 'UTC'),
    ]);

    expect(app(GetProjectHealthAction::class)($project, $nyUser)->isHealthy())->toBeTrue();

    $utcHealth = app(GetProjectHealthAction::class)($project, $utcUser);
    expect($utcHealth->count())->toBe(1)
        ->and($utcHealth->checks->first()->key)->toBe('stuck_doing_tasks')
        ->and($utcHealth->checks->first()->severity)->toBe(HealthCheckSeverity::Warning)
        ->and($utcHealth->checks->first()->title)->toBe('1 task stuck in Doing')
        ->and($utcHealth->checks->first()->count)->toBe(1)
        ->and($utcHealth->checks->first()->linkLabel)->toBe('Review tasks')
        ->and($utcHealth->checks->first()->linkUrl)->toBe(route('project.tasks', $project));

    // Exact boundary in NY: 2026-11-18 05:00:00 UTC (Nov 18 00:00 NY) vs 04:59:59 UTC (Nov 17 23:59:59 NY)
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'status_changed_at' => Carbon::parse('2026-11-18 05:00:00', 'UTC'),
    ]);

    expect(app(GetProjectHealthAction::class)($project, $nyUser)->isHealthy())->toBeTrue();

    // Just over 7 local days in NY, even with updated_at today (simulating ReorderTasksAction)
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'status_changed_at' => Carbon::parse('2026-11-18 04:59:59', 'UTC'),
        'updated_at' => now(),
    ]);

    // Recent status_changed_at with old updated_at must NOT count
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'status_changed_at' => Carbon::parse('2026-11-24 12:00:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-11-01 12:00:00', 'UTC'),
    ]);

    // Todo and Done tasks with old status_changed_at must NOT count
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'status_changed_at' => Carbon::parse('2026-11-01 12:00:00', 'UTC'),
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'status_changed_at' => Carbon::parse('2026-11-01 12:00:00', 'UTC'),
    ]);

    $nyHealth = app(GetProjectHealthAction::class)($project, $nyUser);
    expect($nyHealth->count())->toBe(1)
        ->and($nyHealth->checks->first()->title)->toBe('1 task stuck in Doing')
        ->and($nyHealth->checks->first()->count)->toBe(1);

    // Add a second stuck Doing task for NY to check pluralization
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'status_changed_at' => Carbon::parse('2026-11-10 12:00:00', 'UTC'),
    ]);

    $nyPluralHealth = app(GetProjectHealthAction::class)($project, $nyUser);
    expect($nyPluralHealth->checks->first()->title)->toBe('2 tasks stuck in Doing')
        ->and($nyPluralHealth->checks->first()->count)->toBe(2);
});

test('inbox rule checks 7-day boundary in user timezone and counts only the current user items', function () {
    $this->travelTo(Carbon::parse('2026-11-26 02:00:00', 'UTC'));

    $nyUser = User::factory()->create(['timezone' => 'America/New_York']);
    $otherUser = User::factory()->create(['timezone' => 'America/New_York']);
    $project = Project::factory()->create(['owner_id' => $nyUser->id]);

    // Other user's old inbox item must be ignored
    InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $otherUser->id,
        'created_at' => Carbon::parse('2026-11-01 12:00:00', 'UTC'),
    ]);

    // Soft-deleted item of current user must be ignored
    $deletedItem = InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $nyUser->id,
        'created_at' => Carbon::parse('2026-11-01 12:00:00', 'UTC'),
    ]);
    $deletedItem->delete();

    // Exactly at NY cutoff (Nov 18 00:00 NY == Nov 18 05:00 UTC): just under -> must NOT fire
    InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $nyUser->id,
        'created_at' => Carbon::parse('2026-11-18 05:00:00', 'UTC'),
    ]);

    expect(app(GetProjectHealthAction::class)($project, $nyUser)->isHealthy())->toBeTrue();

    // 1 second before NY cutoff (Nov 17 23:59:59 NY == Nov 18 04:59:59 UTC): just over -> fires
    InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $nyUser->id,
        'created_at' => Carbon::parse('2026-11-18 04:59:59', 'UTC'),
    ]);

    $health = app(GetProjectHealthAction::class)($project, $nyUser);

    expect($health->count())->toBe(1)
        ->and($health->checks->first()->key)->toBe('stale_inbox')
        ->and($health->checks->first()->severity)->toBe(HealthCheckSeverity::Notice)
        ->and($health->checks->first()->title)->toBe('1 idea waiting in the Inbox')
        ->and($health->checks->first()->count)->toBe(1)
        ->and($health->checks->first()->linkLabel)->toBe('Process inbox')
        ->and($health->checks->first()->linkUrl)->toBe(route('project.inbox', $project));

    // Second stale item triggers plural
    InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $nyUser->id,
        'created_at' => Carbon::parse('2026-11-10 12:00:00', 'UTC'),
    ]);

    $pluralHealth = app(GetProjectHealthAction::class)($project, $nyUser);
    expect($pluralHealth->checks->first()->title)->toBe('2 ideas waiting in the Inbox')
        ->and($pluralHealth->checks->first()->count)->toBe(2);
});

test('docs rule fires at 10 tasks completed after the latest notebook change, not at 9, and not without notebooks', function () {
    $this->travelTo(Carbon::parse('2026-11-26 12:00:00', 'UTC'));

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    // Decision exists so decisions check does not interfere
    Decision::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    // 10 completed tasks without any notebooks -> must NOT fire
    Task::factory()->count(10)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'completed_at' => now()->subDay(),
    ]);

    expect(app(GetProjectHealthAction::class)($project, $user)->isHealthy())->toBeTrue();

    // Add notebook changed at Nov 20; 9 tasks completed after it, 1 completed before it -> must NOT fire
    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'content_changed_at' => Carbon::parse('2026-11-20 12:00:00', 'UTC'),
    ]);

    // Reset tasks to test exact 9 vs 10 boundary after notebook change
    $project->tasks()->delete();

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'completed_at' => Carbon::parse('2026-11-20 12:00:00', 'UTC'),
    ]);

    Task::factory()->count(9)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'completed_at' => Carbon::parse('2026-11-21 12:00:00', 'UTC'),
    ]);

    expect(app(GetProjectHealthAction::class)($project, $user)->isHealthy())->toBeTrue();

    // 10th task completed after latest notebook change -> fires
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'completed_at' => Carbon::parse('2026-11-22 12:00:00', 'UTC'),
    ]);

    $health = app(GetProjectHealthAction::class)($project, $user);

    expect($health->count())->toBe(1)
        ->and($health->checks->first()->key)->toBe('stale_docs')
        ->and($health->checks->first()->severity)->toBe(HealthCheckSeverity::Notice)
        ->and($health->checks->first()->title)->toBe('Docs may be out of date')
        ->and($health->checks->first()->count)->toBe(10)
        ->and($health->checks->first()->linkLabel)->toBe('Open notebooks')
        ->and($health->checks->first()->linkUrl)->toBe(route('project.notebooks', $project));

    // A newer notebook change supersedes the older one -> clears the check
    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'content_changed_at' => Carbon::parse('2026-11-23 12:00:00', 'UTC'),
    ]);

    expect(app(GetProjectHealthAction::class)($project, $user)->isHealthy())->toBeTrue();
});

test('decisions rule fires at 10 tasks with zero decisions, not at 9, and not when one decision exists', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->count(9)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
    ]);

    expect(app(GetProjectHealthAction::class)($project, $user)->isHealthy())->toBeTrue();

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
    ]);

    $health = app(GetProjectHealthAction::class)($project, $user);

    expect($health->count())->toBe(1)
        ->and($health->checks->first()->key)->toBe('no_decisions')
        ->and($health->checks->first()->severity)->toBe(HealthCheckSeverity::Notice)
        ->and($health->checks->first()->title)->toBe('No decisions logged yet')
        ->and($health->checks->first()->count)->toBe(10)
        ->and($health->checks->first()->linkLabel)->toBe('Log a decision')
        ->and($health->checks->first()->linkUrl)->toBe(route('project.decisions', $project));

    Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);

    expect(app(GetProjectHealthAction::class)($project, $user)->isHealthy())->toBeTrue();
});

test('broken repository connection rule fires per broken connection with repository and last_error', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    RepositoryConnection::factory()->active()->create([
        'project_id' => $project->id,
        'repository' => 'acme/healthy-repo',
    ]);

    RepositoryConnection::factory()->broken('GitHub rejected the token (401).')->create([
        'project_id' => $project->id,
        'repository' => 'jeyker-dev/ahorra-api',
    ]);

    $health = app(GetProjectHealthAction::class)($project, $user);

    expect($health->count())->toBe(1)
        ->and($health->checks->first()->severity)->toBe(HealthCheckSeverity::Danger)
        ->and($health->checks->first()->title)->toBe('Repository sync is broken')
        ->and($health->checks->first()->explanation)->toBe('jeyker-dev/ahorra-api: GitHub rejected the token (401).')
        ->and($health->checks->first()->linkLabel)->toBe('Fix in settings')
        ->and($health->checks->first()->linkUrl)->toBe(route('project.settings', $project).'#repositories');
});

test('orders checks by severity and runs in a constant number of queries', function () {
    $this->travelTo(Carbon::parse('2026-11-26 12:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    // Trigger notice checks first in creation order
    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'content_changed_at' => now()->subDays(20),
    ]);
    Task::factory()->count(10)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'completed_at' => now()->subDay(),
    ]);
    InboxItem::factory()->count(4)->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'created_at' => now()->subDays(10),
    ]);

    // Trigger warning check
    Task::factory()->count(3)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'status_changed_at' => now()->subDays(10),
    ]);

    // Trigger 2 danger checks
    $connA = RepositoryConnection::factory()->broken('Token expired')->create([
        'project_id' => $project->id,
        'repository' => 'org/repo-a',
    ]);
    $connB = RepositoryConnection::factory()->broken('Repository not found')->create([
        'project_id' => $project->id,
        'repository' => 'org/repo-b',
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $health = app(GetProjectHealthAction::class)($project, $user);

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(2)
        ->and($health->count())->toBe(6)
        ->and($health->checks->pluck('severity')->all())->toBe([
            HealthCheckSeverity::Danger,
            HealthCheckSeverity::Danger,
            HealthCheckSeverity::Warning,
            HealthCheckSeverity::Notice,
            HealthCheckSeverity::Notice,
            HealthCheckSeverity::Notice,
        ])
        ->and($health->checks->pluck('key')->all())->toBe([
            "broken_repository_connection_{$connA->id}",
            "broken_repository_connection_{$connB->id}",
            'stuck_doing_tasks',
            'stale_docs',
            'no_decisions',
            'stale_inbox',
        ]);
});
