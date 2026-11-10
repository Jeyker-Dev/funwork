<?php

namespace App\Data\Mcp;

use Laravel\Mcp\Request;

readonly class GetTaskData
{
    public function __construct(
        public int $taskId,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer'],
        ]);

        return new self(
            taskId: (int) $validated['task_id'],
        );
    }
}
