<?php

namespace App\Actions\Notifications;

use App\Data\DailyDigestData;
use App\Models\InboxItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BuildDailyDigestAction
{
    public function __invoke(User $user): DailyDigestData
    {
        $localToday = $user->localDate();
        $activeProjectIds = $user->projects()
            ->whereNull('archived_at')
            ->pluck('id');

        if ($activeProjectIds->isEmpty()) {
            return new DailyDigestData(
                date: $localToday,
                overdueTasks: new Collection,
                todayTasks: new Collection,
                upcomingTasks: new Collection,
                inboxItems: new Collection,
            );
        }

        /** @return Builder<Task> */
        $baseTaskQuery = fn (): Builder => Task::query()
            ->with('project:id,name')
            ->whereIn('project_id', $activeProjectIds);

        return new DailyDigestData(
            date: $localToday,
            overdueTasks: $baseTaskQuery()
                ->overdue($localToday)
                ->orderBy('due_date')
                ->orderBy('id')
                ->get(),
            todayTasks: $baseTaskQuery()
                ->dueToday($localToday)
                ->orderBy('due_date')
                ->orderBy('id')
                ->get(),
            upcomingTasks: $baseTaskQuery()
                ->dueSoon($localToday)
                ->orderBy('due_date')
                ->orderBy('id')
                ->get(),
            inboxItems: InboxItem::query()
                ->with('project:id,name')
                ->whereIn('project_id', $activeProjectIds)
                ->latest()
                ->orderBy('id', 'desc')
                ->get(),
        );
    }
}
