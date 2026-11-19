<?php

namespace App\Actions\Projects;

use App\Data\DashboardStatsData;
use App\Models\Project;
use App\Models\User;
use App\Support\DateHelper;

class GetDashboardStatsAction
{
    public function __invoke(Project $project, ?User $user = null): DashboardStatsData
    {
        $localToday = DateHelper::userToday($user);

        return new DashboardStatsData(
            openTasksCount: $project->tasks()->open()->count(),
            todayTasksCount: $project->tasks()->dueToday($localToday)->count(),
            inboxCount: $project->inboxItems()->count(),
        );
    }
}
