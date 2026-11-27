<?php

namespace App\Actions\AiConnections;

use App\Data\AiConnections\AiConnectionData;
use App\Exceptions\AiConnections\EmptyAiApiKeyException;
use App\Models\AiConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateAiConnectionAction
{
    public function __construct(
        private readonly ValidateAiConnectionAction $validateConnection,
    ) {}

    public function __invoke(User $user, AiConnectionData $input): AiConnection
    {
        Gate::forUser($user)->authorize('create', AiConnection::class);

        $apiKey = $input->apiKey !== null ? trim($input->apiKey) : '';

        if ($apiKey === '') {
            throw EmptyAiApiKeyException::make();
        }

        return DB::transaction(function () use ($user, $input, $apiKey): AiConnection {
            $attributes = ($this->validateConnection)($input);
            $hasDefault = $user->aiConnections()->where('is_default', true)->exists();

            return AiConnection::create([
                ...$attributes,
                'user_id' => $user->id,
                'api_key' => $apiKey,
                'is_default' => ! $hasDefault,
            ]);
        });
    }
}
