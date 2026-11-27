<?php

namespace App\Actions\AiConnections;

use App\Models\AiConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MakeDefaultAiConnectionAction
{
    public function __invoke(AiConnection $connection, User $user): AiConnection
    {
        Gate::forUser($user)->authorize('makeDefault', $connection);

        return DB::transaction(function () use ($connection): AiConnection {
            AiConnection::query()
                ->where('user_id', $connection->user_id)
                ->where('id', '!=', $connection->id)
                ->update(['is_default' => false]);

            $connection->update(['is_default' => true]);

            return $connection;
        });
    }
}
