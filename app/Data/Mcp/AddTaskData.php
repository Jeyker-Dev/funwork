<?php

namespace App\Data\Mcp;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;

readonly class AddTaskData
{
    public function __construct(
        public int $projectId,
        public string $title,
        public string $description,
        public ?string $areaKey,
        public TaskStatus $status,
        public TaskPriority $priority,
        public ?string $dueDate,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'area' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', 'string', Rule::enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $areaKey = isset($validated['area']) && trim($validated['area']) !== ''
            ? trim($validated['area'])
            : null;

        return new self(
            projectId: (int) $validated['project_id'],
            title: $validated['title'],
            description: $validated['description'],
            areaKey: $areaKey,
            status: isset($validated['status']) ? TaskStatus::from($validated['status']) : TaskStatus::Todo,
            priority: isset($validated['priority']) ? TaskPriority::from($validated['priority']) : TaskPriority::Low,
            dueDate: $validated['due_date'] ?? null,
        );
    }
}
