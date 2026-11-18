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

function sendDigestNotification(User $user, string $date = '2026-11-17'): DatabaseNotification
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

    return $user->notifications()->latest()->firstOrFail();
}

it('renders unread count badge and latest 5 notifications', function () {
    $user = User::factory()->create();

    foreach (range(1, 6) as $day) {
        sendDigestNotification($user, sprintf('2026-11-%02d', 10 + $day));
    }

    Livewire::actingAs($user)
        ->test('layout.notification-bell')
        ->assertSet('unreadCount', 6)
        ->assertCount('notifications', 5)
        ->assertSeeInOrder(['Notifications', '6', 'Daily Digest for 2026-11-16'])
        ->assertDontSee('Daily Digest for 2026-11-11');
});

it('hides unread badge when user has no unread notifications', function () {
    $user = User::factory()->create();
    $notification = sendDigestNotification($user);
    $notification->markAsRead();

    Livewire::actingAs($user)
        ->test('layout.notification-bell')
        ->assertSet('unreadCount', 0)
        ->assertDontSee('Mark all as read');
});

it('marks a single notification as read', function () {
    $user = User::factory()->create();
    $notification = sendDigestNotification($user);

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

    $morningDigest = sendDigestNotification($user, '2026-11-16');
    $todayDigest = sendDigestNotification($user, '2026-11-17');
    $otherDigest = sendDigestNotification($otherUser, '2026-11-17');

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
    $otherNotification = sendDigestNotification($otherUser);

    expect(fn () => Livewire::actingAs($user)
        ->test('layout.notification-bell')
        ->call('markAsRead', $otherNotification->id))
        ->toThrow(ModelNotFoundException::class);

    expect($otherNotification->fresh()->read_at)->toBeNull();
});
