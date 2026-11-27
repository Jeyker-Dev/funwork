<?php

namespace App\Exceptions\AiConnections;

use App\Exceptions\BusinessRuleException;

final class InvalidAiBaseUrlException extends BusinessRuleException
{
    public static function forValue(?string $baseUrl): self
    {
        $suffix = filled($baseUrl) ? " Got \"{$baseUrl}\"." : '';

        return new self("Custom providers require a valid https:// base URL.{$suffix}");
    }
}
