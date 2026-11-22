<?php

namespace App\Actions\Projects;

use App\Enums\ActivityType;
use App\Enums\TaskStatus;
use App\Models\Decision;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

class ListRecentActivitiesAction
{
    /**
     * List the project's most recent notebooks, decisions, files, and completed tasks.
     *
     * @return Collection<int, Notebook|ProjectFile|Decision|Task>
     */
    public function __invoke(Project $project, int $limit): Collection
    {
        $notebooks = $project->notebooks()
            ->select('id', 'name', 'updated_at')
            ->selectRaw('? as type', [ActivityType::Notebook->value]);

        $files = $project->files()
            ->select('id', 'name', 'updated_at')
            ->selectRaw('? as type', [ActivityType::File->value]);

        $decisions = $project->decisions()
            ->select('id', 'title as name', 'updated_at')
            ->selectRaw('? as type', [ActivityType::Decision->value]);

        $completedTasks = $project->tasks()
            ->where('status', TaskStatus::Done->value)
            ->select('id', 'title as name', 'updated_at')
            ->selectRaw('? as type', [ActivityType::Task->value]);

        return $notebooks
            ->unionAll($files)
            ->unionAll($decisions)
            ->unionAll($completedTasks)
            ->withCasts(['type' => ActivityType::class])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
