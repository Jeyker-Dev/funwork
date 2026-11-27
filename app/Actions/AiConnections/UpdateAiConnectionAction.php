<?php

namespace App\Actions\AiConnections;

use App\Data\AiConnections\AiConnectionData;
use App\Models\AiConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateAiConnectionAction
{
    public function __construct(
        private readonly ValidateAiConnectionAction $validateConnection,
    ) {}

    public function __invoke(AiConnection $connection, User $user, AiConnectionData $input): AiConnection
    {
        Gate::forUser($user)->authorize('update', $connection);

        return DB::transaction(function () use ($connection, $input): AiConnection {
            $attributes = ($this->validateConnection)($input);
            $apiKey = $input->apiKey !== null ? trim($input->apiKey) : '';

            if ($apiKey !== '') {
                $attributes['api_key'] = $apiKey;
            }

            $connection->update($attributes);

            return $connection;
        });
    }
}
