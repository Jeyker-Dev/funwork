<?php

namespace App\Data\Mcp;

use App\Data\Notebooks\NotebookData;
use Laravel\Mcp\Request;

readonly class CreateNotebookData
{
    public function __construct(
        public int $projectId,
        public NotebookData $notebook,
        public ?string $areaKey,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
        ]);

        $areaKey = isset($validated['area']) && trim($validated['area']) !== ''
            ? trim($validated['area'])
            : null;

        return new self(
            projectId: (int) $validated['project_id'],
            notebook: new NotebookData(
                name: trim($validated['name']),
                description: filled($validated['description'] ?? null) ? trim($validated['description']) : null,
            ),
            areaKey: $areaKey,
        );
    }
}
