<?php

namespace App\Notifications;

use App\Data\DailyDigestData;
use App\Models\InboxItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class DailyDigestNotification extends Notification
{
    private const int INBOX_PREVIEW_LIMIT = 120;

    /**
     * @var array<string, string>
     */
    private const array SECTION_LABELS = [
        'overdue_tasks' => 'Overdue',
        'today_tasks' => 'Due today',
        'upcoming_tasks' => 'Upcoming',
        'inbox_items' => 'Inbox',
    ];

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
        $channels = ['database'];

        if ($notifiable instanceof User && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        if ($notifiable instanceof User && $notifiable->digest_email_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('FunWork Daily Digest for '.$this->date)
            ->greeting('Daily Digest for '.$this->date)
            ->line($this->summary);

        foreach (self::SECTION_LABELS as $sectionKey => $heading) {
            $items = $this->sections[$sectionKey] ?? [];

            if ($items === []) {
                continue;
            }

            $mail->line(new HtmlString('<strong>'.e($heading).' ('.count($items).')</strong>'));

            foreach ($items as $entry) {
                $mail->line($this->formatMailSectionItem($sectionKey, $entry));
            }
        }

        return $mail->action('View notifications', route('notifications.index'));
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('FunWork Daily Digest')
            ->body($this->summary)
            ->icon('/apple-touch-icon.png')
            ->data(['url' => route('notifications.index')]);
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
     * @param  array<string, mixed>  $entry
     */
    private function formatMailSectionItem(string $sectionKey, array $entry): HtmlString
    {
        $label = $sectionKey === 'inbox_items'
            ? Str::limit((string) $entry['content'], self::INBOX_PREVIEW_LIMIT)
            : (string) $entry['title'];

        $meta = (string) $entry['project_name'];

        if ($sectionKey !== 'inbox_items' && ! empty($entry['due_date'])) {
            $meta .= ' · '.$entry['due_date'];
        }

        return new HtmlString(sprintf(
            '• <a href="%s">%s</a> (%s)',
            e((string) $entry['url']),
            e($label),
            e($meta),
        ));
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
