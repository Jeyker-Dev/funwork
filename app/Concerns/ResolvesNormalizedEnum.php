<?php

namespace App\Concerns;

trait ResolvesNormalizedEnum
{
    public static function resolve(?string $value): ?static
    {
        if ($value === null) {
            return null;
        }

        return static::tryFrom(strtolower(trim($value)));
    }
}
