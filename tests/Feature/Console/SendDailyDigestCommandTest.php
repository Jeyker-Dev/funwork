<?php

use App\Jobs\SendUserDailyDigestJob;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

it('skips users whose local hour does not match digest_hour', function () {
    Queue::fake();
    $this->travelTo(Carbon::parse('2026-11-17 12:00:00', 'UTC'));

    User::factory()->create([
        'timezone' => 'UTC',
        'digest_enabled' => true,
        'digest_hour' => 8,
    ]);

    $this->artisan('funwork:send-daily-digest')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('skips users who already received a digest on their current local date', function () {
    Queue::fake();
    $this->travelTo(Carbon::parse('2026-11-17 13:00:00', 'UTC'));

    User::factory()->create([
        'timezone' => 'America/New_York',
        'digest_enabled' => true,
        'digest_hour' => 8,
        'last_digest_sent_at' => Carbon::parse('2026-11-17 05:30:00', 'UTC'),
    ]);

    $this->artisan('funwork:send-daily-digest')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('skips users with digest_enabled set to false', function () {
    Queue::fake();
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    User::factory()->create([
        'timezone' => 'UTC',
        'digest_enabled' => false,
        'digest_hour' => 8,
    ]);

    $this->artisan('funwork:send-daily-digest')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('dispatches SendUserDailyDigestJob and updates last_digest_sent_at at the user local digest hour', function () {
    Queue::fake();
    $this->travelTo(Carbon::parse('2026-11-17 13:00:00', 'UTC'));

    $user = User::factory()->create([
        'timezone' => 'America/New_York',
        'digest_enabled' => true,
        'digest_hour' => 8,
        'last_digest_sent_at' => Carbon::parse('2026-11-16 13:00:00', 'UTC'),
    ]);

    $this->artisan('funwork:send-daily-digest')
        ->assertSuccessful();

    Queue::assertPushed(SendUserDailyDigestJob::class, fn (SendUserDailyDigestJob $job): bool => $job->user->is($user));
    expect($user->fresh()->last_digest_sent_at?->toIso8601String())
        ->toBe(Carbon::parse('2026-11-17 13:00:00', 'UTC')->toIso8601String());
});

it('skips a targeted user without --force when not due', function () {
    Queue::fake();
    $this->travelTo(Carbon::parse('2026-11-17 20:00:00', 'UTC'));

    $user = User::factory()->create([
        'timezone' => 'UTC',
        'digest_enabled' => true,
        'digest_hour' => 8,
    ]);

    $this->artisan('funwork:send-daily-digest', ['--user' => $user->id])
        ->expectsOutput("Daily digest skipped for user [{$user->id}].")
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('dispatches for all digest-enabled users when --force is passed without --user', function () {
    Queue::fake();
    $this->travelTo(Carbon::parse('2026-11-17 20:00:00', 'UTC'));

    $enabledUser = User::factory()->create([
        'timezone' => 'UTC',
        'digest_enabled' => true,
        'digest_hour' => 8,
    ]);

    $disabledUser = User::factory()->create([
        'timezone' => 'UTC',
        'digest_enabled' => false,
        'digest_hour' => 8,
    ]);

    $this->artisan('funwork:send-daily-digest', ['--force' => true])
        ->expectsOutput('Daily digest dispatched for 1 user(s).')
        ->assertSuccessful();

    Queue::assertPushed(SendUserDailyDigestJob::class, fn (SendUserDailyDigestJob $job): bool => $job->user->is($enabledUser));
    Queue::assertNotPushed(SendUserDailyDigestJob::class, fn (SendUserDailyDigestJob $job): bool => $job->user->is($disabledUser));
});

it('ignores delivery hour and once-per-day guard when --force is passed for a target user', function () {
    Queue::fake();
    $this->travelTo(Carbon::parse('2026-11-17 20:00:00', 'UTC'));

    $user = User::factory()->create([
        'timezone' => 'UTC',
        'digest_enabled' => false,
        'digest_hour' => 8,
        'last_digest_sent_at' => Carbon::parse('2026-11-17 08:00:00', 'UTC'),
    ]);

    $this->artisan('funwork:send-daily-digest', ['--user' => $user->id, '--force' => true])
        ->assertSuccessful();

    Queue::assertPushed(SendUserDailyDigestJob::class, fn (SendUserDailyDigestJob $job): bool => $job->user->is($user));
    expect($user->fresh()->last_digest_sent_at?->toIso8601String())
        ->toBe(Carbon::parse('2026-11-17 20:00:00', 'UTC')->toIso8601String());
});

it('returns failure when --user references a nonexistent user', function () {
    Queue::fake();

    $this->artisan('funwork:send-daily-digest', ['--user' => 99999])
        ->expectsOutput('User [99999] not found.')
        ->assertFailed();

    Queue::assertNothingPushed();
});

it('marks last_digest_sent_at even when the user digest is suppressed for being empty', function () {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-11-17 08:00:00', 'UTC'));

    $user = User::factory()->create([
        'timezone' => 'UTC',
        'digest_enabled' => true,
        'digest_hour' => 8,
        'last_digest_sent_at' => null,
    ]);

    $this->artisan('funwork:send-daily-digest')
        ->assertSuccessful();

    Notification::assertNothingSent();
    expect($user->fresh()->last_digest_sent_at)->not->toBeNull();
});
