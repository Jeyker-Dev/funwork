<?php

namespace App\Data\RepositoryConnections;

use App\Models\Area;

readonly class RepositoryConnectionData
{
    public function __construct(
        public string $repository,
        public string $branch,
        public string $docsPath = 'docs',
        public ?string $token = null,
        public ?Area $area = null,
    ) {}
}
