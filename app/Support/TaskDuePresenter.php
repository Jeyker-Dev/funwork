<?php

namespace App\Support;

use App\Enums\TaskStatus;
use App\Models\Task;
use Carbon\CarbonImmutable;

readonly class TaskDuePresenter
{
    private const int UPCOMING_WEEKDAY_WINDOW_DAYS = 6;

    public function __construct(
        public string $compactLabel,
        public string $panelLabel,
        public bool $isOverdue,
        public bool $isToday,
    ) {}

    public static function for(Task $task, string $localToday): ?self
    {
        if ($task->due_date === null) {
            return null;
        }

        return self::fromDateString($task->due_date->toDateString(), $task->status, $localToday);
    }

    public static function fromDateString(?string $dueDateString, TaskStatus $status, string $localToday): ?self
    {
        if (blank($dueDateString)) {
            return null;
        }

        $today = CarbonImmutable::parse($localToday);
        $dueDate = CarbonImmutable::parse($dueDateString);
        $isDone = $status === TaskStatus::Done;
        $isOverdue = ! $isDone && $dueDate->toDateString() < $today->toDateString();
        $isToday = ! $isDone && $dueDate->toDateString() === $today->toDateString();

        $formattedDate = $dueDate->year !== $today->year
            ? $dueDate->format('M j, Y')
            : $dueDate->format('M j');

        $compactLabel = self::buildCompactLabel($dueDate, $today, $isOverdue, $isToday);
        $panelLabel = self::buildPanelLabel($formattedDate, $isOverdue, $isToday);

        return new self(
            compactLabel: $compactLabel,
            panelLabel: $panelLabel,
            isOverdue: $isOverdue,
            isToday: $isToday,
        );
    }

    private static function buildCompactLabel(
        CarbonImmutable $dueDate,
        CarbonImmutable $today,
        bool $isOverdue,
        bool $isToday,
    ): string {
        if ($isOverdue) {
            return 'Overdue';
        }

        if ($isToday) {
            return 'Due today';
        }

        $daysUntilDue = $today->diffInDays($dueDate, false);

        if ($daysUntilDue >= 1 && $daysUntilDue <= self::UPCOMING_WEEKDAY_WINDOW_DAYS) {
            return 'Due '.$dueDate->format('D');
        }

        return 'Due '.$dueDate->format('M j');
    }

    private static function buildPanelLabel(string $formattedDate, bool $isOverdue, bool $isToday): string
    {
        if ($isOverdue) {
            return "{$formattedDate}, overdue";
        }

        if ($isToday) {
            return "{$formattedDate}, due today";
        }

        return $formattedDate;
    }
}
