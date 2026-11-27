<?php

namespace App\Exceptions\AiConversations;

use App\Exceptions\BusinessRuleException;

final class EmptyAiMessageException extends BusinessRuleException
{
    public static function make(): self
    {
        return new self('Please enter a question before sending.');
    }
}
