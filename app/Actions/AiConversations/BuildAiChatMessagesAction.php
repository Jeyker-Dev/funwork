<?php

namespace App\Actions\AiConversations;

use App\Actions\Projects\BuildProjectContextAction;
use App\Data\AiProviders\AiChatMessage;
use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Support\Collection;

/**
 * Assembles the ordered prompt payload (grounded instructions + live project
 * context, capped Completed history, and the current question) for an AI reply.
 */
class BuildAiChatMessagesAction
{
    public const int MAX_CONTEXT_CHARS = 24_000;

    public const int MAX_HISTORY_CHARS = 12_000;

    public function __construct(
        private readonly BuildProjectContextAction $buildProjectContext,
    ) {}

    /**
     * @return list<AiChatMessage>
     */
    public function __invoke(AiConversation $conversation, AiMessage $currentUserMessage): array
    {
        $rawContext = ($this->buildProjectContext)($conversation->project, $conversation->user);
        $isContextTruncated = mb_strlen($rawContext) > self::MAX_CONTEXT_CHARS;
        $context = $isContextTruncated
            ? mb_substr($rawContext, 0, self::MAX_CONTEXT_CHARS)
            : $rawContext;

        $systemMessage = new AiChatMessage(
            role: AiMessageRole::System,
            content: $this->buildSystemContent($context, $isContextTruncated),
        );

        $historyMessages = $this->cappedHistoryMessages($conversation, $currentUserMessage);

        return [
            $systemMessage,
            ...$historyMessages,
            new AiChatMessage($currentUserMessage->role, $currentUserMessage->content),
        ];
    }

    private function buildSystemContent(string $context, bool $isContextTruncated): string
    {
        $instructions = [
            'You are FunWork\'s project assistant.',
            'Answer only from the project context provided below.',
            'Cite sources inline using ADR codes (for example ADR-001), task IDs (for example Task #12), or notebook page titles.',
            'If the answer is not in the project context, say so clearly instead of guessing.',
            'Refuse questions that are unrelated to this project.',
        ];

        if ($isContextTruncated) {
            $instructions[] = 'Note: The project context below is partial because it exceeded the size limit; mention this if relevant details might be missing.';
        }

        return implode("\n", $instructions)."\n\n## Project Context\n\n".$context;
    }

    /**
     * Selects Completed messages prior to the current question within the history cap,
     * dropping oldest messages first.
     *
     * @return list<AiChatMessage>
     */
    private function cappedHistoryMessages(AiConversation $conversation, AiMessage $currentUserMessage): array
    {
        /** @var Collection<int, AiMessage> $previous */
        $previous = $conversation->messages()
            ->where('id', '<', $currentUserMessage->id)
            ->where('status', AiMessageStatus::Completed->value)
            ->reorder('id', 'desc')
            ->get();

        $selected = [];
        $usedChars = 0;

        foreach ($previous as $message) {
            $length = mb_strlen($message->content);

            if ($usedChars + $length > self::MAX_HISTORY_CHARS) {
                break;
            }

            $usedChars += $length;
            array_unshift($selected, new AiChatMessage($message->role, $message->content));
        }

        return $selected;
    }
}
