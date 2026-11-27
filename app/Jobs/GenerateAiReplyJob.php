<?php

namespace App\Jobs;

use App\Actions\AiConversations\BuildAiChatMessagesAction;
use App\Enums\AiMessageRole;
use App\Exceptions\AiConversations\MissingAiConnectionException;
use App\Exceptions\BusinessRuleException;
use App\Models\AiConnection;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Project;
use App\Models\User;
use App\Services\AiProviders\AiProviderResolver;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Generates an assistant reply in the background for a pending AiMessage.
 */
class GenerateAiReplyJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const int JOB_TIMEOUT_SECONDS = 90;

    private const string GENERIC_ERROR_MESSAGE = 'Something went wrong. Try again.';

    private const string PROJECT_ACCESS_LOST_MESSAGE = 'You no longer have access to this project.';

    public int $tries = 1;

    public int $timeout = self::JOB_TIMEOUT_SECONDS;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public AiMessage $assistantMessage) {}

    public function uniqueId(): string
    {
        return (string) $this->assistantMessage->id;
    }

    public function handle(AiProviderResolver $resolver, BuildAiChatMessagesAction $buildMessages): void
    {
        $message = $this->assistantMessage->fresh([
            'conversation.project',
            'conversation.user',
            'conversation.aiConnection',
        ]);

        if (! $message instanceof AiMessage || ! $message->status->isPending()) {
            return;
        }

        $conversation = $message->conversation;

        if (
            ! $conversation instanceof AiConversation
            || ! $conversation->project instanceof Project
            || ! $conversation->user instanceof User
        ) {
            $message->markFailed(self::GENERIC_ERROR_MESSAGE);

            return;
        }

        if (! $conversation->user->can('view', $conversation->project)) {
            $message->markFailed(self::PROJECT_ACCESS_LOST_MESSAGE);

            return;
        }

        $connection = $conversation->preferredAiConnection();

        if (! $connection instanceof AiConnection) {
            $message->markFailed(MissingAiConnectionException::make()->getMessage());

            return;
        }

        $currentUserMessage = $conversation->messages()
            ->where('role', AiMessageRole::User->value)
            ->where('id', '<', $message->id)
            ->reorder('id', 'desc')
            ->first();

        if (! $currentUserMessage instanceof AiMessage) {
            $message->markFailed(self::GENERIC_ERROR_MESSAGE);

            return;
        }

        try {
            $chatMessages = ($buildMessages)($conversation, $currentUserMessage);
            $reply = $resolver->resolve($connection)->chat($connection, $chatMessages);

            $message->markCompleted($reply->text, $reply->model);
            $conversation->touch();
        } catch (BusinessRuleException $exception) {
            $message->markFailed($exception->getMessage());
        } catch (Throwable $exception) {
            $message->markFailed(self::GENERIC_ERROR_MESSAGE);
            report($this->scrubApiKey($exception, $connection));
        }
    }

    /**
     * Marks the reply as failed when the worker gives up on the job (timeout or
     * crash), so the conversation is never left waiting on a pending reply.
     */
    public function failed(?Throwable $exception): void
    {
        $message = $this->assistantMessage->fresh();

        if ($message instanceof AiMessage && $message->status->isPending()) {
            $message->markFailed(self::GENERIC_ERROR_MESSAGE);
        }
    }

    private function scrubApiKey(Throwable $exception, AiConnection $connection): Throwable
    {
        $apiKey = (string) $connection->api_key;

        if ($apiKey === '' || ! str_contains($exception->getMessage(), $apiKey)) {
            return $exception;
        }

        return new RuntimeException(
            str_replace($apiKey, '[REDACTED]', $exception->getMessage()),
            (int) $exception->getCode(),
        );
    }
}
