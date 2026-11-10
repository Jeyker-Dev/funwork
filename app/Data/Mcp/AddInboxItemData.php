<?php

namespace App\Data\Mcp;

use Laravel\Mcp\Request;

readonly class AddInboxItemData
{
    public function __construct(
        public int $projectId,
        public string $content,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'content' => ['required', 'string', 'max:1000'],
        ]);

        return new self(
            projectId: (int) $validated['project_id'],
            content: $validated['content'],
        );
    }
}
