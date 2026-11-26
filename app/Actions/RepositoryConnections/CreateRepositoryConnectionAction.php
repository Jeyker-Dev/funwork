<?php

namespace App\Actions\RepositoryConnections;

use App\Data\RepositoryConnections\RepositoryConnectionData;
use App\Enums\RepositoryConnectionStatus;
use App\Exceptions\RepositoryConnections\EmptyRepositoryTokenException;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateRepositoryConnectionAction
{
    public function __construct(
        private readonly ValidateRepositoryConnectionAction $validateConnection,
    ) {}

    public function __invoke(Project $project, User $user, RepositoryConnectionData $input): RepositoryConnection
    {
        Gate::forUser($user)->authorize('create', [RepositoryConnection::class, $project]);

        $token = $input->token !== null ? trim($input->token) : '';

        if ($token === '') {
            throw EmptyRepositoryTokenException::make();
        }

        return DB::transaction(function () use ($project, $input, $token): RepositoryConnection {
            ($this->validateConnection)($project, $input);

            $connection = RepositoryConnection::create([
                'project_id' => $project->id,
                'area_id' => $input->area?->id,
                'repository' => $input->repository,
                'branch' => $input->branch,
                'docs_path' => $input->docsPath,
                'token' => $token,
                'webhook_secret' => RepositoryConnection::generateWebhookSecret(),
                'status' => RepositoryConnectionStatus::Active,
            ]);

            if ($input->area !== null) {
                $connection->setRelation('area', $input->area);
            }

            return $connection;
        });
    }
}
