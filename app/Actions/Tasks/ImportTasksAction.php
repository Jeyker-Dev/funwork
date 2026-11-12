<?php

namespace App\Actions\Tasks;

use App\Data\ParsedTaskLineData;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ImportTasksAction
{
    /**
     * @param  array<ParsedTaskLineData>  $lines
     */
    public function __invoke(Project $project, array $lines, User $user): void
    {
        Gate::forUser($user)->authorize('create', [Task::class, $project]);

        DB::transaction(function () use ($project, $lines, $user): void {
            $positions = [];

            foreach ($lines as $line) {
                if (! $line->isValid()) {
                    continue;
                }

                $statusKey = $line->status->value;

                if (! array_key_exists($statusKey, $positions)) {
                    $maxPosition = Task::where('project_id', $project->id)
                        ->where('status', $statusKey)
                        ->max('position');

                    $positions[$statusKey] = $maxPosition ? (int) $maxPosition : 0;
                }

                $positions[$statusKey]++;

                $task = Task::create([
                    'title' => $line->title,
                    'description' => $line->description(),
                    'status' => $line->status,
                    'priority' => $line->priority,
                    'project_id' => $project->id,
                    'position' => $positions[$statusKey],
                    'area_id' => $line->areaId,
                ]);

                $task->users()->sync([$user->id]);
            }
        });
    }
}
