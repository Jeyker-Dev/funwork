<?php

use App\Actions\Notifications\BuildDailyDigestAction;
use App\Enums\TaskStatus;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyDigestNotification;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('redirects guests from notifications index', function () {
    $this->get(route('notifications.index'))
        ->assertRedirect(route('login'));
});

it('displays digest sections with deep links to tasks and project inbox', function () {
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id, 'name' => 'FunWork']);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Ship daily digest',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-17 00:00:00',
    ]);

    InboxItem::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'content' => 'Review inbox idea',
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);
    $user->notifyNow(new DailyDigestNotification($digest));

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertSuccessful()
        ->assertSee('Daily Digest for 2026-11-17')
        ->assertSee('Ship daily digest')
        ->assertSee('Review inbox idea')
        ->assertSee(route('show.task', ['project' => $project->id, 'task' => $task->id]))
        ->assertSee(route('project.inbox', ['project' => $project->id]));
});

it('marks a notification as read from the notifications page', function () {
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-17 00:00:00',
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);
    $user->notifyNow(new DailyDigestNotification($digest));

    $notification = $user->notifications()->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::notifications.index')
        ->call('markAsRead', $notification->id)
        ->assertDispatched('notifications-updated');

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read from the notifications page', function () {
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-17 00:00:00',
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);
    $user->notifyNow(new DailyDigestNotification($digest));
    $user->notifyNow(new DailyDigestNotification($digest));

    Livewire::actingAs($user)
        ->test('pages::notifications.index')
        ->call('markAllAsRead')
        ->assertDispatched('notifications-updated')
        ->assertSet('unreadCount', 0);

    expect($user->unreadNotifications()->count())->toBe(0);
});
