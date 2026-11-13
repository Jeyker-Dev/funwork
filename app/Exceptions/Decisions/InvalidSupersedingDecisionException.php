<?php

namespace App\Exceptions\Decisions;

use App\Exceptions\BusinessRuleException;

final class InvalidSupersedingDecisionException extends BusinessRuleException
{
    public static function missing(): self
    {
        return new self('A superseding decision is required when status is Superseded.');
    }

    public static function differentProject(): self
    {
        return new self('The superseding decision must belong to the same project.');
    }

    public static function cannotSupersedeSelf(): self
    {
        return new self('A decision cannot supersede itself.');
    }
}
