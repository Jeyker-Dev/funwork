<?php

namespace App\Actions\RepositoryConnections;

use App\Models\RepositoryConnection;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RegenerateWebhookSecretAction
{
    public function __invoke(RepositoryConnection $connection, User $user): RepositoryConnection
    {
        Gate::forUser($user)->authorize('update', $connection);

        $connection->update([
            'webhook_secret' => RepositoryConnection::generateWebhookSecret(),
        ]);

        return $connection;
    }
}
