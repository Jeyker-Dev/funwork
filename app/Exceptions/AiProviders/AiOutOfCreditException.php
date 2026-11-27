<?php

namespace App\Exceptions\AiProviders;

use App\Enums\AiProvider;
use App\Exceptions\BusinessRuleException;

final class AiOutOfCreditException extends BusinessRuleException
{
    public static function forProvider(AiProvider $provider): self
    {
        return new self("Your {$provider->label()} account has no credit left.");
    }
}
