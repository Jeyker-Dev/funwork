<?php

namespace App\Exceptions\RepositoryConnections;

use App\Models\RepositoryConnection;
use RuntimeException;

final class RepositoryAccessDeniedException extends RuntimeException
{
    public static function forConnection(RepositoryConnection $connection, int $status): self
    {
        return new self("GitHub denied access to [{$connection->repository}] on branch [{$connection->branch}] (HTTP {$status}).");
    }
}
