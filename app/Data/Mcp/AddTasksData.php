<?php

namespace App\Data\Mcp;

use Laravel\Mcp\Request;

readonly class AddTasksData
{
    public const int MAX_TASKS_PER_CALL = 25;

    /**
     * @param  list<AddTaskData>  $tasks
     */
    public function __construct(
        public int $projectId,
        public array $tasks,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'tasks' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_TASKS_PER_CALL],
            'tasks.*' => ['required', 'array'],
            ...AddTaskData::itemRules('tasks.*.'),
        ]);

        $projectId = (int) $validated['project_id'];

        $tasks = array_map(
            fn (array $taskAttributes): AddTaskData => AddTaskData::fromValidated($projectId, $taskAttributes),
            array_values($validated['tasks']),
        );

        return new self(
            projectId: $projectId,
            tasks: $tasks,
        );
    }
}
