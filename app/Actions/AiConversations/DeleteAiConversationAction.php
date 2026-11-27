<?php

namespace App\Actions\AiConversations;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteAiConversationAction
{
    public function __invoke(AiConversation $conversation, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $conversation);

        $conversation->delete();
    }
}
