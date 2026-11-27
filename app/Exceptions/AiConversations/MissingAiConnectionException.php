<?php

namespace App\Exceptions\AiConversations;

use App\Exceptions\BusinessRuleException;

final class MissingAiConnectionException extends BusinessRuleException
{
    public static function make(): self
    {
        return new self('No AI connection configured. Add one in Settings → Integrations.');
    }
}
