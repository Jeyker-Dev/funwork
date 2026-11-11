<?php

namespace App\Data\Mcp;

use App\Models\Task;
use Laravel\Mcp\Request;

readonly class UpdateTaskDescriptionData
{
    public function __construct(
        public int $taskId,
        public string $description,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:'.Task::MAX_DESCRIPTION_LENGTH],
        ]);

        return new self(
            taskId: (int) $validated['task_id'],
            description: $validated['description'],
        );
    }
}
