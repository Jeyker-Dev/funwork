<?php

namespace App\Actions\RepositoryConnections;

use App\Jobs\SyncRepositoryDocsJob;
use App\Models\RepositoryConnection;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Authorizes and queues an immediate docs sync for a repository connection.
 */
class SyncRepositoryConnectionNowAction
{
    public function __invoke(RepositoryConnection $connection, User $user): void
    {
        Gate::forUser($user)->authorize('update', $connection);

        SyncRepositoryDocsJob::dispatch($connection);
    }
}
