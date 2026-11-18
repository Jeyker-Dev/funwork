<?php

namespace App\Jobs;

use App\Actions\Notifications\BuildDailyDigestAction;
use App\Models\User;
use App\Notifications\DailyDigestNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendUserDailyDigestJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user) {}

    public function handle(BuildDailyDigestAction $buildDigest): void
    {
        $digest = $buildDigest($this->user);

        if ($digest->isEmpty()) {
            return;
        }

        $this->user->notify(new DailyDigestNotification($digest));
    }
}
