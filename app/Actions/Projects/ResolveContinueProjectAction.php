<?php

namespace App\Actions\Projects;

use App\Data\Projects\ContinueProjectData;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

class ResolveContinueProjectAction
{
    /**
     * Pick the most recently active project and its next open task.
     *
     * Most recently active: the owned project with the highest timestamp across
     * `projects.updated_at` and the latest `updated_at` of its tasks, notebooks, and non-deleted files.
     * Next task: the open task with the earliest `due_date`, falling back to the oldest open task.
     *
     * @param  Collection<int, Project>  $projects
     */
    public function __invoke(Collection $projects): ?ContinueProjectData
    {
        if ($projects->isEmpty()) {
            return null;
        }

        /** @var Project $mostRecentProject */
        $mostRecentProject = $projects
            ->sort(fn (Project $leftProject, Project $rightProject): int => $rightProject->compareRecentActivityTo($leftProject))
            ->first();

        return new ContinueProjectData(
            project: $mostRecentProject,
            totalTasksCount: $mostRecentProject->totalTasksCount(),
            doneTasksCount: $mostRecentProject->doneTasksCount(),
            progressPercent: $mostRecentProject->taskProgressPercent(),
            lastActivityAt: $mostRecentProject->lastActivityAt(),
            nextTask: $this->findNextTask($mostRecentProject),
        );
    }

    private function findNextTask(Project $project): ?Task
    {
        return $project->tasks()
            ->open()
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('due_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }
}
