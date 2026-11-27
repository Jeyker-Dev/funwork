<?php

namespace App\Data\Projects;

use App\Data\DashboardStatsData;
use App\Models\Area;
use App\Models\Decision;
use App\Models\Notebook;
use App\Models\ProjectFile;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

readonly class ProjectHomeData
{
    private const int UPCOMING_WEEKDAY_WINDOW_DAYS = 6;

    /**
     * @param  Collection<int, Task>  $todayTasks
     * @param  Collection<int, Task>  $upcomingTasks
     * @param  Collection<int, Area>  $areas
     * @param  Collection<int, Notebook|ProjectFile|Decision|Task>  $recentActivities
     */
    public function __construct(
        public DashboardStatsData $stats,
        public ProjectHealthData $health,
        public Collection $todayTasks,
        public Collection $upcomingTasks,
        public ?Task $nextOpenTask,
        public Collection $areas,
        public Collection $recentActivities,
        public string $localToday,
    ) {}

    public function formatUpcomingDueLabel(Task $task): ?string
    {
        if ($task->due_date === null) {
            return null;
        }

        $today = CarbonImmutable::parse($this->localToday);
        $dueDate = CarbonImmutable::parse($task->due_date->toDateString());
        $daysUntilDue = $today->diffInDays($dueDate, false);

        return $daysUntilDue >= 1 && $daysUntilDue <= self::UPCOMING_WEEKDAY_WINDOW_DAYS
            ? $dueDate->format('l')
            : $dueDate->format('M j');
    }

    public function upNextText(): ?string
    {
        if ($this->nextOpenTask === null) {
            return null;
        }

        $dayLabel = $this->formatUpcomingDueLabel($this->nextOpenTask);

        if ($dayLabel === null) {
            return "Up next: {$this->nextOpenTask->title}";
        }

        return "Up next: {$this->nextOpenTask->title}, due {$dayLabel}";
    }
}
