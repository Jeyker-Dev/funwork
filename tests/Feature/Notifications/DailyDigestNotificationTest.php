<?php

use App\Actions\Notifications\BuildDailyDigestAction;
use App\Enums\TaskStatus;
use App\Jobs\SendUserDailyDigestJob;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyDigestNotification;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\ReportHandler;
use NotificationChannels\WebPush\WebPushChannel;

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

it('uses only the database channel when the user has no push subscriptions', function () {
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $digest = app(BuildDailyDigestAction::class)($user);
    $notification = new DailyDigestNotification($digest);

    expect($notification->via($user))->toBe(['database']);
});

it('includes WebPushChannel when the user has an active push subscription', function () {
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/sub1', 'key', 'auth');

    $digest = app(BuildDailyDigestAction::class)($user);
    $notification = new DailyDigestNotification($digest);

    expect($notification->via($user))->toBe(['database', WebPushChannel::class]);
});

it('formats a minimal Web Push payload with counts only and notification index url', function () {
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id, 'name' => 'Secret Project']);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Confidential Task Title',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-17 00:00:00',
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);
    $notification = new DailyDigestNotification($digest);
    $payload = $notification->toWebPush($user, $notification)->toArray();

    expect($payload['title'])->toBe('FunWork Daily Digest')
        ->and($payload['body'])->toBe('You have 1 task due today.')
        ->and($payload['body'])->not->toContain('Confidential Task Title')
        ->and($payload['body'])->not->toContain('Secret Project')
        ->and($payload['icon'])->toBe('/apple-touch-icon.png')
        ->and($payload['data'])->toBe(['url' => route('notifications.index')]);
});

it('deletes expired push subscriptions when the push service returns 404 or 410', function (int $statusCode) {
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-17 00:00:00',
    ]);

    $subscription = $user->updatePushSubscription(
        endpoint: 'https://fcm.googleapis.com/fcm/send/expired-sub',
        key: 'public-key',
        token: 'auth-token',
    );

    $digest = app(BuildDailyDigestAction::class)($user);
    $notification = new DailyDigestNotification($digest);

    $report = new MessageSentReport(
        new Psr7Request('POST', $subscription->endpoint),
        new Psr7Response($statusCode),
        false,
        'Expired subscription',
    );

    $webPush = Mockery::mock(WebPush::class);
    $webPush->shouldReceive('queueNotification')->once();
    $webPush->shouldReceive('flush')->once()->andReturn((function () use ($report): Generator {
        yield $report;
    })());

    $channel = new WebPushChannel($webPush, app(ReportHandler::class));
    $channel->send($user, $notification);

    expect(PushSubscription::find($subscription->id))->toBeNull();
})->with([
    'not found (404)' => 404,
    'gone (410)' => 410,
]);

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

it('excludes mail channel by default when dispatching digest job', function () {
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

    Notification::assertSentTo(
        $user,
        DailyDigestNotification::class,
        fn (DailyDigestNotification $notification, array $channels): bool => $channels === ['database'],
    );
});

it('includes mail channel in via() and dispatch when digest_email_enabled is true', function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create([
        'timezone' => 'UTC',
        'digest_email_enabled' => true,
    ]);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-17 00:00:00',
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);
    expect((new DailyDigestNotification($digest))->via($user))->toBe(['database', 'mail']);

    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/sub-mail', 'key', 'auth');
    expect((new DailyDigestNotification($digest))->via($user))->toBe(['database', WebPushChannel::class, 'mail']);

    (new SendUserDailyDigestJob($user))->handle(app(BuildDailyDigestAction::class));

    Notification::assertSentTo(
        $user,
        DailyDigestNotification::class,
        fn (DailyDigestNotification $notification, array $channels): bool => $channels === ['database', WebPushChannel::class, 'mail'],
    );
});

it('suppresses notification when digest is empty even if digest_email_enabled is true', function () {
    Notification::fake();

    $user = User::factory()->create([
        'timezone' => 'UTC',
        'digest_email_enabled' => true,
    ]);

    (new SendUserDailyDigestJob($user))->handle(app(BuildDailyDigestAction::class));

    Notification::assertNothingSent();
});

it('formats mail message with summary, sections, deep links, and notifications button', function () {
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create([
        'timezone' => 'UTC',
        'digest_email_enabled' => true,
    ]);
    $project = Project::factory()->create(['owner_id' => $user->id, 'name' => 'Alpha']);

    $overdueTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => '[Backend] Fix overdue sync',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-16 00:00:00',
    ]);

    $todayTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Ship daily digest email',
        'status' => TaskStatus::Doing,
        'due_date' => '2026-11-17 00:00:00',
    ]);

    $upcomingTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Review Brevo quota',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-19 00:00:00',
    ]);

    InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'content' => 'Follow up on sender domain',
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);
    $mail = (new DailyDigestNotification($digest))->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('FunWork Daily Digest for 2026-11-17')
        ->and($mail->actionUrl)->toBe(route('notifications.index'))
        ->and($html)->toContain('You have 1 overdue task, 1 task due today, 1 upcoming task and 1 inbox item.')
        ->and($html)->toContain('[Backend] Fix overdue sync')
        ->and($html)->toContain(route('show.task', ['project' => $project->id, 'task' => $overdueTask->id]))
        ->and($html)->toContain('Ship daily digest email')
        ->and($html)->toContain(route('show.task', ['project' => $project->id, 'task' => $todayTask->id]))
        ->and($html)->toContain('Review Brevo quota')
        ->and($html)->toContain(route('show.task', ['project' => $project->id, 'task' => $upcomingTask->id]))
        ->and($html)->toContain('Follow up on sender domain')
        ->and($html)->toContain(route('project.inbox', ['project' => $project->id]))
        ->and($html)->toContain(route('notifications.index'));
});
