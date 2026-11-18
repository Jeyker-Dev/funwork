<?php

namespace App\Notifications;

use App\Data\DailyDigestData;
use App\Models\InboxItem;
use App\Models\Task;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class DailyDigestNotification extends Notification
{
    public string $date;

    public string $summary;

    public int $overdueCount;

    public int $todayCount;

    public int $upcomingCount;

    public int $inboxCount;

    /**
     * @var array{
     *     overdue_tasks: list<array{id: int, project_id: int, project_name: string, title: string, due_date: ?string, url: string}>,
     *     today_tasks: list<array{id: int, project_id: int, project_name: string, title: string, due_date: ?string, url: string}>,
     *     upcoming_tasks: list<array{id: int, project_id: int, project_name: string, title: string, due_date: ?string, url: string}>,
     *     inbox_items: list<array{id: int, project_id: int, project_name: string, content: string, url: string}>
     * }
     */
    public array $sections;

    public function __construct(DailyDigestData $digest)
    {
        $this->date = $digest->date;
        $this->summary = $digest->summaryText();
        $this->overdueCount = $digest->overdueCount();
        $this->todayCount = $digest->todayCount();
        $this->upcomingCount = $digest->upcomingCount();
        $this->inboxCount = $digest->inboxCount();
        $this->sections = [
            'overdue_tasks' => $this->serializeTasks($digest->overdueTasks),
            'today_tasks' => $this->serializeTasks($digest->todayTasks),
            'upcoming_tasks' => $this->serializeTasks($digest->upcomingTasks),
            'inbox_items' => $this->serializeInboxItems($digest->inboxItems),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'daily_digest',
            'title' => 'Daily Digest for '.$this->date,
            'summary' => $this->summary,
            'overdue_count' => $this->overdueCount,
            'today_count' => $this->todayCount,
            'upcoming_count' => $this->upcomingCount,
            'inbox_count' => $this->inboxCount,
            'sections' => $this->sections,
            'url' => route('notifications.index'),
        ];
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return list<array{id: int, project_id: int, project_name: string, title: string, due_date: ?string, url: string}>
     */
    private function serializeTasks(Collection $tasks): array
    {
        return $tasks->map(fn (Task $task): array => [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'project_name' => $task->projectName(),
            'title' => $task->title,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'url' => route('show.task', [
                'project' => $task->project_id,
                'task' => $task->id,
            ]),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, InboxItem>  $inboxItems
     * @return list<array{id: int, project_id: int, project_name: string, content: string, url: string}>
     */
    private function serializeInboxItems(Collection $inboxItems): array
    {
        return $inboxItems->map(fn (InboxItem $inboxItem): array => [
            'id' => $inboxItem->id,
            'project_id' => $inboxItem->project_id,
            'project_name' => $inboxItem->projectName(),
            'content' => $inboxItem->content,
            'url' => route('project.inbox', [
                'project' => $inboxItem->project_id,
            ]),
        ])->values()->all();
    }
}
