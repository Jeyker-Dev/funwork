<?php

namespace App\Data\Mcp;

use App\Enums\TaskStatus;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;

readonly class ListTasksData
{
    public function __construct(
        public int $projectId,
        public ?TaskStatus $status,
        public ?string $area,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'status' => ['nullable', 'string', Rule::enum(TaskStatus::class)],
            'area' => ['nullable', 'string'],
        ]);

        $area = isset($validated['area']) && trim($validated['area']) !== ''
            ? trim($validated['area'])
            : null;

        return new self(
            projectId: (int) $validated['project_id'],
            status: ! empty($validated['status']) ? TaskStatus::from($validated['status']) : null,
            area: $area,
        );
    }
}
