<?php

namespace App\Data\Mcp;

use Laravel\Mcp\Request;

readonly class GetProjectBriefData
{
    public function __construct(
        public int $projectId,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
        ]);

        return new self(
            projectId: (int) $validated['project_id'],
        );
    }
}
