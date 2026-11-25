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

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function itemRules(string $prefix = ''): array
    {
        return [
            "{$prefix}title" => ['required', 'string', 'max:255'],
            "{$prefix}description" => ['required', 'string'],
            "{$prefix}area" => ['nullable', 'string'],
            "{$prefix}status" => ['nullable', 'string', Rule::enum(TaskStatus::class)],
            "{$prefix}priority" => ['nullable', 'string', Rule::enum(TaskPriority::class)],
            "{$prefix}due_date" => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            ...self::itemRules(),
        ]);

        return self::fromValidated((int) $validated['project_id'], $validated);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromValidated(int $projectId, array $attributes): self
    {
        $areaKey = isset($attributes['area']) && trim($attributes['area']) !== ''
            ? trim($attributes['area'])
            : null;

        return new self(
            projectId: $projectId,
            title: $attributes['title'],
            description: $attributes['description'],
            areaKey: $areaKey,
            status: filled($attributes['status'] ?? null) ? TaskStatus::from($attributes['status']) : TaskStatus::Todo,
            priority: filled($attributes['priority'] ?? null) ? TaskPriority::from($attributes['priority']) : TaskPriority::Low,
            dueDate: filled($attributes['due_date'] ?? null) ? $attributes['due_date'] : null,
        );
    }
}
