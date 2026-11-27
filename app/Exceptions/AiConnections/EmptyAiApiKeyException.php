<?php

namespace App\Exceptions\AiConnections;

use App\Exceptions\BusinessRuleException;

final class EmptyAiApiKeyException extends BusinessRuleException
{
    public static function make(): self
    {
        return new self('An API key is required to create an AI connection.');
    }
}
