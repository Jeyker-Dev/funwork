<?php

namespace App\Exceptions\AiConversations;

use App\Exceptions\BusinessRuleException;

final class CannotRetryAiReplyException extends BusinessRuleException
{
    public static function make(): self
    {
        return new self('Only the latest failed reply can be retried.');
    }
}
