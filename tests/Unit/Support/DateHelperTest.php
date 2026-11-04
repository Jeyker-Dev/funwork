<?php

use App\Models\User;
use App\Support\DateHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it calculates the local date for a specific user timezone', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00'));

    $userNY = User::factory()->make(['timezone' => 'America/New_York']);
    $userUTC = User::factory()->make(['timezone' => 'UTC']);

    expect(DateHelper::userToday($userNY))->toBe('2026-11-03')
        ->and(DateHelper::userToday($userUTC))->toBe('2026-11-04');
});

test('it calculates the local date using authenticated user when user parameter is omitted', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00'));

    $user = User::factory()->create(['timezone' => 'America/New_York']);
    $this->actingAs($user);

    expect(DateHelper::userToday())->toBe('2026-11-03');
});

test('it falls back to app timezone when user timezone is null or unauthenticated', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00'));

    $userNoTz = User::factory()->make(['timezone' => null]);

    expect(DateHelper::userToday($userNoTz))->toBe('2026-11-04')
        ->and(DateHelper::userToday())->toBe('2026-11-04');
});

test('user model provides localDate method delegating to the helper', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00'));

    $userNY = User::factory()->make(['timezone' => 'America/New_York']);
    $userUTC = User::factory()->make(['timezone' => 'UTC']);

    expect($userNY->localDate())->toBe('2026-11-03')
        ->and($userUTC->localDate())->toBe('2026-11-04');
});
