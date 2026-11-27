<?php

namespace App\Exceptions\AiProviders;

use App\Enums\AiProvider;
use App\Exceptions\BusinessRuleException;

final class AiKeyRejectedException extends BusinessRuleException
{
    public static function forProvider(AiProvider $provider): self
    {
        return new self("Your {$provider->label()} key was rejected. Update it in Settings → Integrations.");
    }
}
