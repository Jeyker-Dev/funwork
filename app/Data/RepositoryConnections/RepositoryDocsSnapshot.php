<?php

namespace App\Data\RepositoryConnections;

readonly class RepositoryDocsSnapshot
{
    /**
     * @param  array<string, string>  $files
     * @param  list<array{path: string, reason: string}>  $skipped
     */
    public function __construct(
        public string $commitSha,
        public array $files,
        public array $skipped = [],
    ) {}
}
