<?php

namespace App\Actions\AiConversations;

use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Exceptions\AiConversations\AiMessageTooLongException;
use App\Exceptions\AiConversations\AiRateLimitExceededException;
use App\Exceptions\AiConversations\AiReplyPendingException;
use App\Exceptions\AiConversations\EmptyAiMessageException;
use App\Jobs\GenerateAiReplyJob;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class SendAiMessageAction
{
    public const int MAX_CONTENT_LENGTH = 4_000;

    public const int MAX_MESSAGES_PER_HOUR = 30;

    public const string RATE_LIMITER_NAME = 'ai-messages';

    public function __invoke(AiConversation $conversation, User $user, string $content): AiMessage
    {
        Gate::forUser($user)->authorize('send', $conversation);

        $trimmed = trim($content);

        if ($trimmed === '') {
            throw EmptyAiMessageException::make();
        }

        if (mb_strlen($trimmed) > self::MAX_CONTENT_LENGTH) {
            throw AiMessageTooLongException::max(self::MAX_CONTENT_LENGTH);
        }

        $limit = $this->resolveRateLimit($user);

        if (RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts)) {
            throw AiRateLimitExceededException::forSeconds(RateLimiter::availableIn($limit->key));
        }

        $assistantMessage = DB::transaction(function () use ($conversation, $trimmed): AiMessage {
            $lockedConversation = AiConversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            if ($lockedConversation->hasPendingReply()) {
                throw AiReplyPendingException::make();
            }

            $lockedConversation->messages()->create([
                'role' => AiMessageRole::User,
                'content' => $trimmed,
                'status' => AiMessageStatus::Completed,
            ]);

            if (blank($lockedConversation->title)) {
                $lockedConversation->title = $lockedConversation->generateTitleFromQuestion($trimmed);
            }

            $lockedConversation->updated_at = now();
            $lockedConversation->save();

            return $lockedConversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => '',
                'status' => AiMessageStatus::Pending,
            ]);
        });

        RateLimiter::hit($limit->key, $limit->decaySeconds);

        GenerateAiReplyJob::dispatch($assistantMessage);

        return $assistantMessage;
    }

    private function resolveRateLimit(User $user): Limit
    {
        $limiter = RateLimiter::limiter(self::RATE_LIMITER_NAME);

        if ($limiter !== null) {
            $limit = $limiter($user);

            if ($limit instanceof Limit) {
                return $limit;
            }
        }

        return Limit::perHour(self::MAX_MESSAGES_PER_HOUR)->by('ai-messages:'.$user->getKey());
    }
}
