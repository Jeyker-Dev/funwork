<?php

namespace App\Actions\RepositoryConnections;

use App\Models\RepositoryConnection;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteRepositoryConnectionAction
{
    public function __invoke(RepositoryConnection $connection, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $connection);

        $connection->delete();
    }
}
