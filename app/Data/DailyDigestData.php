<?php

namespace App\Data;

use App\Models\InboxItem;
use App\Models\Task;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

readonly class DailyDigestData
{
    private const int SINGULAR_COUNT = 1;

    /**
     * @param  Collection<int, Task>  $overdueTasks
     * @param  Collection<int, Task>  $todayTasks
     * @param  Collection<int, Task>  $upcomingTasks
     * @param  Collection<int, InboxItem>  $inboxItems
     */
    public function __construct(
        public string $date,
        public Collection $overdueTasks,
        public Collection $todayTasks,
        public Collection $upcomingTasks,
        public Collection $inboxItems,
    ) {}

    public function isEmpty(): bool
    {
        return $this->overdueTasks->isEmpty()
            && $this->todayTasks->isEmpty()
            && $this->upcomingTasks->isEmpty()
            && $this->inboxItems->isEmpty();
    }

    public function summaryText(): string
    {
        $parts = array_values(array_filter([
            $this->overdueTasks->isNotEmpty() ? $this->pluralize($this->overdueTasks->count(), 'overdue task', 'overdue tasks') : null,
            $this->todayTasks->isNotEmpty() ? $this->pluralize($this->todayTasks->count(), 'task due today', 'tasks due today') : null,
            $this->upcomingTasks->isNotEmpty() ? $this->pluralize($this->upcomingTasks->count(), 'upcoming task', 'upcoming tasks') : null,
            $this->inboxItems->isNotEmpty() ? $this->pluralize($this->inboxItems->count(), 'inbox item', 'inbox items') : null,
        ]));

        if ($parts === []) {
            return 'Nothing pending for today.';
        }

        return 'You have '.Arr::join($parts, ', ', ' and ').'.';
    }

    private function pluralize(int $count, string $singular, string $plural): string
    {
        return $count === self::SINGULAR_COUNT ? "1 {$singular}" : "{$count} {$plural}";
    }
}
