<?php

namespace App\Exceptions\AiConversations;

use App\Exceptions\BusinessRuleException;

final class AiMessageTooLongException extends BusinessRuleException
{
    public static function max(int $maxLength): self
    {
        return new self("Your message is too long. Maximum length is {$maxLength} characters.");
    }
}
