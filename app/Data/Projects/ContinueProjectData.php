<?php

namespace App\Data\Projects;

use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonInterface;

readonly class ContinueProjectData
{
    public function __construct(
        public Project $project,
        public int $totalTasksCount,
        public int $doneTasksCount,
        public int $progressPercent,
        public CarbonInterface $lastActivityAt,
        public ?Task $nextTask,
    ) {}
}
