<?php

namespace App\Exceptions\AiConversations;

use App\Exceptions\BusinessRuleException;

final class AiReplyPendingException extends BusinessRuleException
{
    public static function make(): self
    {
        return new self('Please wait for the current reply to finish before sending another message.');
    }
}
