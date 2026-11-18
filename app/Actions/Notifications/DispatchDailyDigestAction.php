<?php

namespace App\Actions\Notifications;

use App\Jobs\SendUserDailyDigestJob;
use App\Models\User;

class DispatchDailyDigestAction
{
    public function __invoke(User $user): void
    {
        $user->markDailyDigestSent();
        SendUserDailyDigestJob::dispatch($user);
    }
}
