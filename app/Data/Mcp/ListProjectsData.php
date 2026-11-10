<?php

namespace App\Data\Mcp;

use Laravel\Mcp\Request;

readonly class ListProjectsData
{
    public function __construct(
        public bool $includeArchived,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'include_archived' => ['sometimes', 'boolean'],
        ]);

        return new self(
            includeArchived: (bool) ($validated['include_archived'] ?? false),
        );
    }
}
