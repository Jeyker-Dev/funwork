<?php

namespace App\Actions\Projects;

use App\Enums\ActivityType;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Database\Eloquent\Collection;

class ListRecentActivitiesAction
{
    /**
     * List the project's most recently updated notebooks and files, tagged with their ActivityType.
     *
     * @return Collection<int, Notebook|ProjectFile>
     */
    public function __invoke(Project $project, int $limit): Collection
    {
        $notebooks = $project->notebooks()
            ->select('id', 'name', 'created_by', 'updated_at')
            ->selectRaw('? as type', [ActivityType::Notebook->value]);

        $files = $project->files()
            ->select('id', 'name', 'created_by', 'updated_at')
            ->selectRaw('? as type', [ActivityType::File->value]);

        return $notebooks->unionAll($files)
            ->withCasts(['type' => ActivityType::class])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
