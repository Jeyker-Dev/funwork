<?php

namespace App\Data\Mcp;

use App\Data\Projects\ProjectData;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;

readonly class CreateProjectData
{
    public function __construct(
        public ProjectData $project,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', Rule::enum(ProjectType::class)],
            'status' => ['nullable', 'string', Rule::enum(ProjectStatus::class)],
        ]);

        return new self(
            project: new ProjectData(
                name: trim($validated['name']),
                description: filled($validated['description'] ?? null) ? trim($validated['description']) : null,
                icon: ProjectData::DEFAULT_ICON,
                type: filled($validated['type'] ?? null) ? $validated['type'] : ProjectData::DEFAULT_TYPE,
                status: filled($validated['status'] ?? null) ? $validated['status'] : ProjectData::DEFAULT_STATUS,
            ),
        );
    }
}
