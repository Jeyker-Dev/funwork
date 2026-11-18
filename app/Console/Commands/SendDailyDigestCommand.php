<?php

namespace App\Console\Commands;

use App\Actions\Notifications\DispatchDailyDigestAction;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('funwork:send-daily-digest {--user= : Optional user ID to target} {--force : Ignore delivery hour and daily idempotency guard}')]
#[Description('Dispatch daily workload digest notifications for eligible users')]
class SendDailyDigestCommand extends Command
{
    public function __construct(
        private DispatchDailyDigestAction $dispatchDigest,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = $this->option('user');
        $targetUser = $userId !== null ? User::find((string) $userId) : null;

        if ($userId !== null && ! $targetUser instanceof User) {
            $this->error("User [{$userId}] not found.");

            return self::FAILURE;
        }

        $users = $targetUser instanceof User
            ? collect([$targetUser])
            : User::query()->where('digest_enabled', true)->lazyById();

        $targets = $this->option('force')
            ? $users
            : $users->filter(fn (User $user): bool => $user->isDailyDigestDue());

        $dispatched = 0;

        foreach ($targets as $user) {
            ($this->dispatchDigest)($user);
            $dispatched++;
        }

        $this->info("Daily digest dispatched for {$dispatched} user(s).");

        return self::SUCCESS;
    }
}
