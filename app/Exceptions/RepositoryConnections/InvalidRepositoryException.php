<?php

namespace App\Exceptions\RepositoryConnections;

use App\Exceptions\BusinessRuleException;

final class InvalidRepositoryException extends BusinessRuleException
{
    public static function forValue(string $repository): self
    {
        return new self("Repository [{$repository}] must be in owner/name format.");
    }
}
