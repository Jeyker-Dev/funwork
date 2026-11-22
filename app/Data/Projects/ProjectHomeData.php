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
     * @param  Collection<int, Area>  $areas
     * @param  Collection<int, Notebook|ProjectFile|Decision|Task>  $recentActivities
     */
    public function __construct(
        public DashboardStatsData $stats,
        public Collection $todayTasks,
        public ?Task $nextOpenTask,
        public Collection $areas,
        public Collection $recentActivities,
        public string $localToday,
    ) {}

    public function upNextText(): ?string
    {
        if ($this->nextOpenTask === null) {
            return null;
        }

        if ($this->nextOpenTask->due_date === null) {
            return "Up next: {$this->nextOpenTask->title}";
        }

        $today = CarbonImmutable::parse($this->localToday);
        $dueDate = CarbonImmutable::parse($this->nextOpenTask->due_date->toDateString());
        $daysUntilDue = $today->diffInDays($dueDate, false);

        $dayLabel = $daysUntilDue >= 1 && $daysUntilDue <= self::UPCOMING_WEEKDAY_WINDOW_DAYS
            ? $dueDate->format('l')
            : $dueDate->format('M j');

        return "Up next: {$this->nextOpenTask->title}, due {$dayLabel}";
    }
}
