<?php

namespace App\Exceptions\RepositoryConnections;

use App\Exceptions\BusinessRuleException;

final class InvalidBranchException extends BusinessRuleException
{
    public static function forValue(string $branch): self
    {
        return new self("Branch [{$branch}] must be a valid relative branch name without '..' or a leading slash.");
    }
}
