<?php

namespace App\Exceptions\RepositoryConnections;

use App\Models\RepositoryConnection;
use RuntimeException;

final class RepositoryRateLimitedException extends RuntimeException
{
    public static function forConnection(RepositoryConnection $connection, int $status): self
    {
        return new self("GitHub API rate limit exceeded while syncing [{$connection->repository}] (HTTP {$status}).");
    }
}
