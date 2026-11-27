<?php

namespace App\Exceptions\AiConversations;

use App\Exceptions\BusinessRuleException;

final class AiConversationTitleTooLongException extends BusinessRuleException
{
    public static function max(int $maxLength): self
    {
        return new self("Conversation title cannot exceed {$maxLength} characters.");
    }
}
