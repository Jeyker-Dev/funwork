<?php

use App\Actions\Notifications\BuildDailyDigestAction;
use App\Enums\TaskStatus;
use App\Jobs\SendUserDailyDigestJob;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyDigestNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

it('dispatches DailyDigestNotification when the user has items', function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-17 00:00:00',
    ]);

    (new SendUserDailyDigestJob($user))->handle(app(BuildDailyDigestAction::class));

    Notification::assertSentTo($user, DailyDigestNotification::class);
});

it('suppresses notification when the digest is empty', function () {
    Notification::fake();

    $user = User::factory()->create(['timezone' => 'UTC']);

    (new SendUserDailyDigestJob($user))->handle(app(BuildDailyDigestAction::class));

    Notification::assertNothingSent();
});

it('uses the database delivery channel', function () {
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $digest = app(BuildDailyDigestAction::class)($user);
    $notification = new DailyDigestNotification($digest);

    expect($notification->via($user))->toBe(['database']);
});

it('formats database payload metadata and counts', function () {
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id, 'name' => 'Alpha']);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-16 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'due_date' => '2026-11-17 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-19 00:00:00',
    ]);

    InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);
    $payload = (new DailyDigestNotification($digest))->toDatabase($user);

    expect($payload['type'])->toBe('daily_digest')
        ->and($payload['title'])->toBe('Daily Digest for 2026-11-17')
        ->and($payload['summary'])->toBe('You have 1 overdue task, 1 task due today, 1 upcoming task and 1 inbox item.')
        ->and($payload['overdue_count'])->toBe(1)
        ->and($payload['today_count'])->toBe(1)
        ->and($payload['upcoming_count'])->toBe(1)
        ->and($payload['inbox_count'])->toBe(1)
        ->and($payload['url'])->toBe(route('notifications.index'));
});

it('includes task and project inbox deep links in database payload sections', function () {
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id, 'name' => 'Alpha']);

    $overdueTask = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-16 00:00:00',
    ]);

    $todayTask = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'due_date' => '2026-11-17 00:00:00',
    ]);

    $upcomingTask = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-19 00:00:00',
    ]);

    $inboxItem = InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);
    $payload = (new DailyDigestNotification($digest))->toDatabase($user);

    expect($payload['sections']['overdue_tasks'][0]['url'])->toBe(route('show.task', ['project' => $project->id, 'task' => $overdueTask->id]))
        ->and($payload['sections']['today_tasks'][0]['url'])->toBe(route('show.task', ['project' => $project->id, 'task' => $todayTask->id]))
        ->and($payload['sections']['upcoming_tasks'][0]['url'])->toBe(route('show.task', ['project' => $project->id, 'task' => $upcomingTask->id]))
        ->and($payload['sections']['inbox_items'][0]['url'])->toBe(route('project.inbox', ['project' => $project->id]))
        ->and($payload['sections']['inbox_items'][0]['id'])->toBe($inboxItem->id);
});
