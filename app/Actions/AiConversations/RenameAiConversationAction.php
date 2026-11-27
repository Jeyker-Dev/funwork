<?php

namespace App\Actions\AiConversations;

use App\Exceptions\AiConversations\AiConversationTitleTooLongException;
use App\Exceptions\AiConversations\EmptyAiConversationTitleException;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RenameAiConversationAction
{
    public function __invoke(AiConversation $conversation, User $user, string $title): AiConversation
    {
        Gate::forUser($user)->authorize('update', $conversation);

        $trimmed = trim($title);

        if ($trimmed === '') {
            throw EmptyAiConversationTitleException::make();
        }

        if (mb_strlen($trimmed) > AiConversation::TITLE_MAX_LENGTH) {
            throw AiConversationTitleTooLongException::max(AiConversation::TITLE_MAX_LENGTH);
        }

        $conversation->update(['title' => $trimmed]);

        return $conversation;
    }
}
