<?php

namespace App\Data;

readonly class DashboardStatsData
{
    public function __construct(
        public int $openTasksCount,
        public int $todayTasksCount,
        public int $inboxCount,
    ) {}

    /**
     * Today's tasks when there are any, otherwise every open task.
     */
    public function highlightedTasksCount(): int
    {
        return $this->todayTasksCount ?: $this->openTasksCount;
    }
}
