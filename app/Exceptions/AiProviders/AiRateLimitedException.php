<?php

namespace App\Exceptions\AiProviders;

use App\Enums\AiProvider;
use App\Exceptions\BusinessRuleException;

final class AiRateLimitedException extends BusinessRuleException
{
    public static function forProvider(AiProvider $provider): self
    {
        return new self("You reached {$provider->label()}'s usage limit. Try again later.");
    }

    public static function forOpenRouterFree(): self
    {
        return new self("You reached OpenRouter's usage limit. Try again later. It resets at midnight UTC.");
    }
}
