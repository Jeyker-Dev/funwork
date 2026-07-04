<?php

namespace App\Livewire\Actions;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Reorders tasks across columns after a drag-and-drop operation.
 *
 * Receives the full payload: { "Todo": [3, 1, 2], "Doing": [5, 4], "Done": [6] }
 * Updates each task's status and position atomically.
 */
class ReorderTasks
{
    public function __invoke(array $payload, int $projectId): void
    {
        DB::transaction(function () use ($payload, $projectId) {
            foreach ($payload as $status => $orderedIds) {
                foreach ($orderedIds as $index => $id) {
                    Task::where('project_id', $projectId)
                        ->where('id', $id)
                        ->update([
                            'status' => $status,
                            'position' => $index + 1,
                        ]);
                }
            }
        });
    }
}
