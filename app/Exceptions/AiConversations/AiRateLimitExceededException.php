<?php

namespace App\Exceptions\AiConversations;

use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Str;

final class AiRateLimitExceededException extends BusinessRuleException
{
    public static function forSeconds(int $seconds): self
    {
        $minutes = max(1, (int) ceil($seconds / 60));
        $unit = Str::plural('minute', $minutes);

        return new self("You sent too many messages. Try again in {$minutes} {$unit}.");
    }
}
