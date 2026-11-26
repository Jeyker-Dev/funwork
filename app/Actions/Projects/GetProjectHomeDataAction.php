<?php

namespace App\Actions\Projects;

use App\Data\Projects\ProjectHealthData;
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

    private const int TODAY_SPARE_ROOM_TARGET = 5;

    public function __construct(
        private readonly GetDashboardStatsAction $getDashboardStats,
        private readonly ListRecentActivitiesAction $listRecentActivities,
        private readonly GetProjectHealthAction $getProjectHealth,
    ) {}

    /**
     * Assemble all data for the project Home view without N+1 queries.
     */
    public function __invoke(Project $project, ?User $user = null): ProjectHomeData
    {
        $resolvedUser = $user ?? auth()->user();
        $timezone = DateHelper::userTimezone($resolvedUser);
        $localToday = DateHelper::userToday($resolvedUser);

        $todayTasks = $this->loadTodayTasks($project, $localToday, $timezone);
        $remainingSlots = max(0, self::TODAY_SPARE_ROOM_TARGET - $todayTasks->count());
        $upcomingTasks = $remainingSlots > 0
            ? $this->loadUpcomingTasks($project, $localToday, $remainingSlots)
            : new Collection;

        $health = $resolvedUser instanceof User
            ? ($this->getProjectHealth)($project, $resolvedUser)
            : ProjectHealthData::empty();

        return new ProjectHomeData(
            stats: ($this->getDashboardStats)($project, $resolvedUser),
            health: $health,
            todayTasks: $todayTasks,
            upcomingTasks: $upcomingTasks,
            nextOpenTask: $todayTasks->isEmpty() ? $upcomingTasks->first() : null,
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

    /**
     * @return Collection<int, Task>
     */
    private function loadUpcomingTasks(Project $project, string $localToday, int $limit): Collection
    {
        return $project->tasks()
            ->with('area')
            ->open()
            ->where(function (Builder $query) use ($localToday): void {
                $query->whereNull('due_date')
                    ->orWhereDate('due_date', '>', $localToday);
            })
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('due_date')
            ->orderBy('position')
            ->orderBy('id')
            ->limit($limit)
            ->get();
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
