<?php

namespace App\Exceptions\AiConversations;

use App\Exceptions\BusinessRuleException;

final class EmptyAiConversationTitleException extends BusinessRuleException
{
    public static function make(): self
    {
        return new self('Conversation title cannot be empty.');
    }
}
