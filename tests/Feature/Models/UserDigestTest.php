<?php

use App\Models\User;
use Illuminate\Support\Carbon;

test('markDailyDigestSent records current timestamp', function () {
    $this->travelTo(Carbon::parse('2026-11-17 08:05:00', 'UTC'));

    $user = User::factory()->create(['last_digest_sent_at' => null]);

    $user->markDailyDigestSent();

    expect($user->fresh()->last_digest_sent_at?->toIso8601String())
        ->toBe(Carbon::parse('2026-11-17 08:05:00', 'UTC')->toIso8601String());
});
