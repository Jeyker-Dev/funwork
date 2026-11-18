<?php

use App\Actions\Notifications\BuildDailyDigestAction;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyDigestNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function createDigestNotification(User $user, string $date = '2026-11-17'): void
{
    Carbon::setTestNow(Carbon::parse("{$date} 08:00:00", 'UTC'));

    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => "{$date} 00:00:00",
    ]);

    $digest = app(BuildDailyDigestAction::class)($user);
    $user->notifyNow(new DailyDigestNotification($digest));

    Carbon::setTestNow();
}

function latestDigestNotification(User $user): DatabaseNotification
{
    return $user->notifications()->latest()->firstOrFail();
}

it('renders unread count badge for unread notifications', function () {
    $user = User::factory()->create();

    foreach (range(1, 6) as $day) {
        createDigestNotification($user, sprintf('2026-11-%02d', 10 + $day));
    }

    Livewire::actingAs($user)
        ->test('layout.notification-bell')
        ->assertSet('unreadCount', 6)
        ->assertSeeInOrder(['Notifications', '6']);
});

it('limits dropdown notifications to 5 items', function () {
    $user = User::factory()->create();

    foreach (range(1, 6) as $day) {
        createDigestNotification($user, sprintf('2026-11-%02d', 10 + $day));
    }

    Livewire::actingAs($user)
        ->test('layout.notification-bell')
        ->assertCount('notifications', 5)
        ->assertDontSee('Daily Digest for 2026-11-11');
});

it('orders dropdown notifications newest first', function () {
    $user = User::factory()->create();

    createDigestNotification($user, '2026-11-15');
    createDigestNotification($user, '2026-11-16');

    Livewire::actingAs($user)
        ->test('layout.notification-bell')
        ->assertSeeInOrder(['Daily Digest for 2026-11-16', 'Daily Digest for 2026-11-15']);
});

it('hides unread badge when user has no unread notifications', function () {
    $user = User::factory()->create();
    createDigestNotification($user);
    latestDigestNotification($user)->markAsRead();

    Livewire::actingAs($user)
        ->test('layout.notification-bell')
        ->assertSet('unreadCount', 0)
        ->assertDontSee('Mark all as read');
});

it('marks a single notification as read', function () {
    $user = User::factory()->create();
    createDigestNotification($user);
    $notification = latestDigestNotification($user);

    Livewire::actingAs($user)
        ->test('layout.notification-bell')
        ->call('markAsRead', $notification->id)
        ->assertDispatched('notifications-updated')
        ->assertSet('unreadCount', 0);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all unread notifications as read only for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    createDigestNotification($user, '2026-11-16');
    $morningDigest = latestDigestNotification($user);

    createDigestNotification($user, '2026-11-17');
    $todayDigest = latestDigestNotification($user);

    createDigestNotification($otherUser, '2026-11-17');
    $otherDigest = latestDigestNotification($otherUser);

    Livewire::actingAs($user)
        ->test('layout.notification-bell')
        ->call('markAllAsRead')
        ->assertDispatched('notifications-updated')
        ->assertSet('unreadCount', 0);

    expect($morningDigest->fresh()->read_at)->not->toBeNull()
        ->and($todayDigest->fresh()->read_at)->not->toBeNull()
        ->and($otherDigest->fresh()->read_at)->toBeNull();
});

it('rejects marking another users notification as read', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    createDigestNotification($otherUser);
    $otherNotification = latestDigestNotification($otherUser);

    expect(fn () => Livewire::actingAs($user)
        ->test('layout.notification-bell')
        ->call('markAsRead', $otherNotification->id))
        ->toThrow(ModelNotFoundException::class);

    expect($otherNotification->fresh()->read_at)->toBeNull();
});

it('refreshes unread count and list when notification-received is dispatched', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('layout.notification-bell')
        ->assertSet('unreadCount', 0)
        ->assertCount('notifications', 0);

    createDigestNotification($user, '2026-11-18');

    $component
        ->dispatch('notification-received')
        ->assertSet('unreadCount', 1)
        ->assertCount('notifications', 1)
        ->assertSee('Daily Digest for 2026-11-18');
});
