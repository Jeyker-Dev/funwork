<?php

namespace App\Exceptions\AiConnections;

use App\Exceptions\BusinessRuleException;

final class EmptyAiModelException extends BusinessRuleException
{
    public static function make(): self
    {
        return new self('A model ID is required for an AI connection.');
    }
}
