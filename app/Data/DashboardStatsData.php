<?php

namespace App\Data;

use App\Enums\TaskStatus;

readonly class DashboardStatsData
{
    public function __construct(
        public int $openTasksCount,
        public int $todayTasksCount,
        public int $inboxCount,
        public int $totalTasksCount = 0,
        public int $todoTasksCount = 0,
        public int $doingTasksCount = 0,
        public int $doneTasksCount = 0,
        public int $progressPercent = 0,
    ) {}

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        return [
            TaskStatus::Todo->value => $this->todoTasksCount,
            TaskStatus::Doing->value => $this->doingTasksCount,
            TaskStatus::Done->value => $this->doneTasksCount,
        ];
    }
}
