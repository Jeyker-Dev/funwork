<?php

namespace App\Data\Mcp;

use Laravel\Mcp\Request;

readonly class ReadNotebookData
{
    public function __construct(
        public ?int $notebookId,
        public ?int $projectId,
        public ?string $name,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'notebook_id' => ['nullable', 'integer', 'required_without:project_id'],
            'project_id' => ['nullable', 'integer', 'required_without:notebook_id'],
            'name' => ['nullable', 'string', 'required_with:project_id'],
        ]);

        return new self(
            notebookId: ! empty($validated['notebook_id']) ? (int) $validated['notebook_id'] : null,
            projectId: ! empty($validated['project_id']) ? (int) $validated['project_id'] : null,
            name: $validated['name'] ?? null,
        );
    }
}
