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

        if ($userId !== null) {
            return $this->option('force')
                ? $this->forceSingleUser((string) $userId)
                : $this->dispatchSingleUserIfDue((string) $userId);
        }

        $users = User::query()->where('digest_enabled', true)->lazyById();
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

    private function forceSingleUser(string $userId): int
    {
        $user = User::find($userId);

        if (! $user instanceof User) {
            $this->error("User [{$userId}] not found.");

            return self::FAILURE;
        }

        ($this->dispatchDigest)($user);
        $this->info('Daily digest dispatched for 1 user(s).');

        return self::SUCCESS;
    }

    private function dispatchSingleUserIfDue(string $userId): int
    {
        $user = User::find($userId);

        if (! $user instanceof User) {
            $this->error("User [{$userId}] not found.");

            return self::FAILURE;
        }

        if (! $user->isDailyDigestDue()) {
            $this->info("Daily digest skipped for user [{$user->id}].");

            return self::SUCCESS;
        }

        ($this->dispatchDigest)($user);
        $this->info('Daily digest dispatched for 1 user(s).');

        return self::SUCCESS;
    }
}
