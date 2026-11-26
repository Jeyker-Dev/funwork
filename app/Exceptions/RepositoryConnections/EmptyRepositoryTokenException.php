<?php

namespace App\Exceptions\RepositoryConnections;

use App\Exceptions\BusinessRuleException;

final class EmptyRepositoryTokenException extends BusinessRuleException
{
    public static function make(): self
    {
        return new self('A personal access token is required to create a repository connection.');
    }
}
