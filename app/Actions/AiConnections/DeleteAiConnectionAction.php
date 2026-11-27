<?php

namespace App\Actions\AiConnections;

use App\Models\AiConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteAiConnectionAction
{
    public function __invoke(AiConnection $connection, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $connection);

        DB::transaction(function () use ($connection): void {
            $wasDefault = $connection->is_default;
            $userId = $connection->user_id;

            $connection->delete();

            if (! $wasDefault) {
                return;
            }

            $replacement = AiConnection::query()
                ->where('user_id', $userId)
                ->latest('id')
                ->first();

            $replacement?->update(['is_default' => true]);
        });
    }
}
