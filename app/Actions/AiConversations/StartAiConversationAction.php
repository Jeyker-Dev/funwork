<?php

namespace App\Actions\AiConversations;

use App\Exceptions\AiConversations\MissingAiConnectionException;
use App\Models\AiConnection;
use App\Models\AiConversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class StartAiConversationAction
{
    public function __invoke(User $user, Project $project): AiConversation
    {
        Gate::forUser($user)->authorize('create', [AiConversation::class, $project]);

        $connection = $user->aiConnections()
            ->where('is_default', true)
            ->first()
            ?? $user->aiConnections()->latest('id')->first();

        if (! $connection instanceof AiConnection) {
            throw MissingAiConnectionException::make();
        }

        return AiConversation::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'ai_connection_id' => $connection->id,
        ]);
    }
}
