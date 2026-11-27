<?php

namespace App\Exceptions\AiProviders;

use App\Enums\AiProvider;
use App\Exceptions\BusinessRuleException;

final class AiProviderUnavailableException extends BusinessRuleException
{
    public static function forProvider(AiProvider $provider): self
    {
        return new self("{$provider->label()} is not responding. Try again in a moment.");
    }
}
