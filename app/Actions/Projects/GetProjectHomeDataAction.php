<?php

namespace App\Actions\Projects;

use App\Data\Projects\ProjectHomeData;
use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetProjectHomeDataAction
{
    private const int RECENT_ACTIVITY_LIMIT = 5;

    public function __construct(
        private readonly GetDashboardStatsAction $getDashboardStats,
        private readonly ListRecentActivitiesAction $listRecentActivities,
    ) {}

    /**
     * Assemble all data for the project Home view without N+1 queries.
     */
    public function __invoke(Project $project, ?User $user = null): ProjectHomeData
    {
        $timezone = DateHelper::userTimezone($user);
        $localToday = DateHelper::userToday($user);

        $todayTasks = $this->loadTodayTasks($project, $localToday, $timezone);
        $nextOpenTask = $todayTasks->isEmpty() ? $this->findNextOpenTask($project) : null;

        return new ProjectHomeData(
            stats: ($this->getDashboardStats)($project, $user),
            todayTasks: $todayTasks,
            nextOpenTask: $nextOpenTask,
            areas: $this->loadAreasWithProgress($project),
            recentActivities: ($this->listRecentActivities)($project, self::RECENT_ACTIVITY_LIMIT),
            localToday: $localToday,
        );
    }

    /**
     * @return Collection<int, Task>
     */
    private function loadTodayTasks(Project $project, string $localToday, string $timezone): Collection
    {
        $localDayStartUtc = CarbonImmutable::parse($localToday, $timezone)->startOfDay()->utc();
        $localDayEndUtc = CarbonImmutable::parse($localToday, $timezone)->endOfDay()->utc();

        return $project->tasks()
            ->with('area')
            ->where(function (Builder $query) use ($localToday, $localDayStartUtc, $localDayEndUtc): void {
                $query->where(fn (Builder $openQuery): Builder => $openQuery->urgent($localToday))
                    ->orWhere(fn (Builder $doneQuery): Builder => $doneQuery
                        ->where('status', TaskStatus::Done->value)
                        ->whereBetween('updated_at', [$localDayStartUtc, $localDayEndUtc]));
            })
            ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END ASC', [TaskStatus::Done->value])
            ->orderBy('due_date')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    private function findNextOpenTask(Project $project): ?Task
    {
        return $project->tasks()
            ->open()
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('due_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return Collection<int, Area>
     */
    private function loadAreasWithProgress(Project $project): Collection
    {
        return $project->areas()
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn (Builder $query): Builder => $query->where('status', TaskStatus::Done->value),
            ])
            ->get();
    }
}
