<?php

namespace App\Actions\AiConversations;

use App\Exceptions\AiConversations\CannotRetryAiReplyException;
use App\Jobs\GenerateAiReplyJob;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RetryAiReplyAction
{
    public function __invoke(AiMessage $assistantMessage, User $user): AiMessage
    {
        $conversation = $assistantMessage->conversation;
        Gate::forUser($user)->authorize('send', $conversation);

        $retriedMessage = DB::transaction(function () use ($conversation, $assistantMessage): AiMessage {
            $lockedConversation = AiConversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            $latestAssistant = $lockedConversation->latestAssistantMessage();

            if (
                ! $latestAssistant instanceof AiMessage
                || $latestAssistant->id !== $assistantMessage->id
                || ! $latestAssistant->status->isFailed()
            ) {
                throw CannotRetryAiReplyException::make();
            }

            $latestAssistant->resetToPending();
            $lockedConversation->touch();

            return $latestAssistant;
        });

        GenerateAiReplyJob::dispatch($retriedMessage);

        return $retriedMessage;
    }
}
