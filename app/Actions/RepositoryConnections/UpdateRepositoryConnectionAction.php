<?php

namespace App\Actions\RepositoryConnections;

use App\Data\RepositoryConnections\RepositoryConnectionData;
use App\Models\RepositoryConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateRepositoryConnectionAction
{
    public function __construct(
        private readonly ValidateRepositoryConnectionAction $validateConnection,
    ) {}

    public function __invoke(
        RepositoryConnection $connection,
        User $user,
        RepositoryConnectionData $input,
    ): RepositoryConnection {
        Gate::forUser($user)->authorize('update', $connection);

        return DB::transaction(function () use ($connection, $input): RepositoryConnection {
            ($this->validateConnection)($connection->project, $input, $connection);

            $attributes = [
                'area_id' => $input->area?->id,
                'repository' => $input->repository,
                'branch' => $input->branch,
                'docs_path' => $input->docsPath,
            ];

            if ($this->syncSourceChanges($connection, $attributes)) {
                $attributes['last_synced_sha'] = null;
                $attributes['last_synced_at'] = null;
            }

            $token = $input->token !== null ? trim($input->token) : '';

            if ($token !== '') {
                $attributes['token'] = $token;
            }

            $connection->update($attributes);

            if ($input->area !== null) {
                $connection->setRelation('area', $input->area);
            } else {
                $connection->unsetRelation('area');
            }

            return $connection;
        });
    }

    /**
     * Whether the update points the sync at a different repository, branch, folder or notebook,
     * so the next sync must run even for an already synced commit.
     *
     * @param  array{area_id: ?int, repository: string, branch: string, docs_path: string}  $attributes
     */
    private function syncSourceChanges(RepositoryConnection $connection, array $attributes): bool
    {
        return $connection->repository !== $attributes['repository']
            || $connection->branch !== $attributes['branch']
            || $connection->docs_path !== $attributes['docs_path']
            || $connection->area_id !== $attributes['area_id'];
    }
}
