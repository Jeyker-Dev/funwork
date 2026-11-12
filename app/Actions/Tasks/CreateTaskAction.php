<?php

namespace App\Actions\Tasks;

use App\Data\Mcp\AddTaskData;
use App\Models\Area;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateTaskAction
{
    public function __invoke(Project $project, User $user, AddTaskData $taskInput, ?Area $area = null): Task
    {
        Gate::forUser($user)->authorize('create', [Task::class, $project]);

        return DB::transaction(function () use ($project, $user, $taskInput, $area): Task {
            $maxPosition = Task::where('project_id', $project->id)
                ->where('status', $taskInput->status->value)
                ->max('position');

            $position = ($maxPosition ? (int) $maxPosition : 0) + 1;

            $task = Task::create([
                'project_id' => $project->id,
                'title' => $taskInput->title,
                'description' => $taskInput->description,
                'status' => $taskInput->status,
                'priority' => $taskInput->priority,
                'due_date' => $taskInput->dueDate,
                'position' => $position,
                'area_id' => $area?->id,
            ]);

            $task->users()->sync([$user->id]);

            if ($area !== null) {
                $task->setRelation('area', $area);
            }

            return $task;
        });
    }
}
