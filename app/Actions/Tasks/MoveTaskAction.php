<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\QueryHelper;
use Illuminate\Support\Facades\Gate;

/**
 * Moves a single task to a target status column and 0-based visible position,
 * delegating persistence and sequence normalization to ReorderTasksAction.
 */
class MoveTaskAction
{
    public function __construct(
        private readonly ReorderTasksAction $reorderTasks,
    ) {}

    public function __invoke(Task $task, TaskStatus $targetStatus, int $position, ?string $areaFilter = null): void
    {
        Gate::authorize('update', $task);

        $sourceStatus = $task->status;
        $targetIds = $this->orderedIdsForStatus($task->project_id, $targetStatus);
        $targetIds = array_values(array_filter($targetIds, static fn (int $id): bool => $id !== $task->id));

        $insertAt = $this->resolveInsertIndex($task, $targetStatus, $targetIds, $position, $areaFilter);
        array_splice($targetIds, $insertAt, 0, [$task->id]);

        $payload = [
            $targetStatus->value => $targetIds,
        ];

        if ($sourceStatus !== $targetStatus) {
            $sourceIds = $this->orderedIdsForStatus($task->project_id, $sourceStatus);
            $payload[$sourceStatus->value] = array_values(
                array_filter($sourceIds, static fn (int $id): bool => $id !== $task->id)
            );
        }

        ($this->reorderTasks)($payload, $task->project_id);
    }

    /**
     * @return array<int, int>
     */
    private function orderedIdsForStatus(int $projectId, TaskStatus $status): array
    {
        return Task::query()
            ->where('project_id', $projectId)
            ->where('status', $status->value)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $targetIds
     */
    private function resolveInsertIndex(
        Task $task,
        TaskStatus $targetStatus,
        array $targetIds,
        int $visiblePosition,
        ?string $areaFilter,
    ): int {
        $boundedPosition = max(0, $visiblePosition);

        if (blank($areaFilter) || $areaFilter === 'all') {
            return min($boundedPosition, count($targetIds));
        }

        $visibleQuery = Task::query()
            ->where('project_id', $task->project_id)
            ->where('status', $targetStatus->value)
            ->where('id', '!=', $task->id)
            ->orderBy('position')
            ->orderBy('id');

        QueryHelper::applyAreaFilter($visibleQuery, $areaFilter);

        $visibleIds = $visibleQuery->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($boundedPosition >= count($visibleIds)) {
            return count($targetIds);
        }

        $anchorId = $visibleIds[$boundedPosition];
        $anchorIndex = array_search($anchorId, $targetIds, true);

        return $anchorIndex === false ? count($targetIds) : (int) $anchorIndex;
    }
}
