<?php

namespace App\Data\Mcp;

use App\Enums\TaskStatus;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;

readonly class UpdateTaskStatusData
{
    public function __construct(
        public int $taskId,
        public TaskStatus $status,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer'],
            'status' => ['required', 'string', Rule::enum(TaskStatus::class)],
        ]);

        return new self(
            taskId: (int) $validated['task_id'],
            status: TaskStatus::from($validated['status']),
        );
    }
}
