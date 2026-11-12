<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Reorders tasks across columns after a drag-and-drop operation.
 *
 * Receives the full payload: { "Todo": [3, 1, 2], "Doing": [5, 4], "Done": [6] }
 * Updates each task's status and position atomically.
 */
class ReorderTasksAction
{
    public function __invoke(array $payload, int $projectId): void
    {
        $project = Project::findOrFail($projectId);
        Gate::authorize('update', $project);

        foreach (array_keys($payload) as $statusKey) {
            if (TaskStatus::tryFrom((string) $statusKey) === null) {
                throw ValidationException::withMessages([
                    'status' => ["Invalid task status: {$statusKey}."],
                ]);
            }
        }

        DB::transaction(function () use ($payload, $projectId) {
            foreach ($payload as $statusKey => $orderedIds) {
                $status = TaskStatus::from((string) $statusKey);

                foreach ($orderedIds as $index => $id) {
                    $task = Task::where('project_id', $projectId)
                        ->where('id', $id)
                        ->first();

                    if ($task) {
                        Gate::authorize('update', $task);

                        $task->update([
                            'status' => $status,
                            'position' => $index + 1,
                        ]);
                    }
                }
            }
        });
    }
}
