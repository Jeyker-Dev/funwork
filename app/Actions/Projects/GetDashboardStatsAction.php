<?php

namespace App\Actions\Projects;

use App\Data\DashboardStatsData;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use App\Support\DateHelper;
use Illuminate\Database\Eloquent\Builder;

class GetDashboardStatsAction
{
    public function __invoke(Project $project, ?User $user = null): DashboardStatsData
    {
        $localToday = DateHelper::userToday($user);

        $project->loadCount([
            'tasks',
            'tasks as todo_tasks_count' => fn (Builder $query): Builder => $query->where('status', TaskStatus::Todo->value),
            'tasks as doing_tasks_count' => fn (Builder $query): Builder => $query->where('status', TaskStatus::Doing->value),
            'tasks as done_tasks_count' => fn (Builder $query): Builder => $query->where('status', TaskStatus::Done->value),
            'tasks as today_tasks_count' => fn (Builder $query): Builder => $query->dueToday($localToday),
            'inboxItems',
        ]);

        $totalTasksCount = $project->totalTasksCount();
        $doneTasksCount = $project->doneTasksCount();

        return new DashboardStatsData(
            openTasksCount: $totalTasksCount - $doneTasksCount,
            todayTasksCount: (int) $project->today_tasks_count,
            inboxCount: (int) $project->inbox_items_count,
            totalTasksCount: $totalTasksCount,
            todoTasksCount: (int) $project->todo_tasks_count,
            doingTasksCount: (int) $project->doing_tasks_count,
            doneTasksCount: $doneTasksCount,
            progressPercent: $project->taskProgressPercent(),
        );
    }
}
