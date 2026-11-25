<?php

namespace App\Exceptions\RepositoryConnections;

use App\Models\RepositoryConnection;
use RuntimeException;

final class RepositoryTreeTruncatedException extends RuntimeException
{
    public static function forCommit(RepositoryConnection $connection, string $commitSha): self
    {
        return new self("GitHub returned a truncated file tree for [{$connection->repository}] at commit [{$commitSha}].");
    }
}
