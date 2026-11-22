<?php

namespace App\Data\Projects;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

readonly class ProjectsOverviewData
{
    /**
     * @param  Collection<int, Project>  $projects
     */
    public function __construct(
        public string $greeting,
        public int $activeProjectsCount,
        public int $tasksDueThisWeekCount,
        public ?ContinueProjectData $continueProject,
        public Collection $projects,
    ) {}

    public function summaryText(): string
    {
        $projectWord = Str::plural('project', $this->activeProjectsCount);
        $taskWord = Str::plural('task', $this->tasksDueThisWeekCount);

        return "{$this->activeProjectsCount} active {$projectWord}, {$this->tasksDueThisWeekCount} {$taskWord} due this week";
    }
}
