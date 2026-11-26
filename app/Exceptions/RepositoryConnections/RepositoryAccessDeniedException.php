<?php

namespace App\Exceptions\RepositoryConnections;

use App\Models\RepositoryConnection;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class RepositoryAccessDeniedException extends RuntimeException
{
    public static function forConnection(RepositoryConnection $connection, int $status): self
    {
        return new self(
            self::messageFor($connection, $status),
            $status,
        );
    }

    private static function messageFor(RepositoryConnection $connection, int $status): string
    {
        return match ($status) {
            Response::HTTP_UNAUTHORIZED => "GitHub authentication failed for [{$connection->repository}] (HTTP 401): token expired or invalid.",
            Response::HTTP_FORBIDDEN => "GitHub denied access to [{$connection->repository}] (HTTP 403): check token permissions.",
            Response::HTTP_NOT_FOUND => "GitHub repository [{$connection->repository}] or branch [{$connection->branch}] was not found (HTTP 404).",
            default => "GitHub denied access to [{$connection->repository}] on branch [{$connection->branch}] (HTTP {$status}).",
        };
    }
}
