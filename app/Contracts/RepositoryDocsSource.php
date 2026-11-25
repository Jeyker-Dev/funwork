<?php

namespace App\Contracts;

use App\Data\RepositoryConnections\RepositoryDocsSnapshot;
use App\Models\RepositoryConnection;

interface RepositoryDocsSource
{
    /**
     * Resolve the current head commit SHA for the connection's branch.
     */
    public function headCommit(RepositoryConnection $connection): string;

    /**
     * Fetch a complete snapshot of eligible markdown documentation at a commit.
     */
    public function fetchDocs(RepositoryConnection $connection, string $commitSha): RepositoryDocsSnapshot;
}
