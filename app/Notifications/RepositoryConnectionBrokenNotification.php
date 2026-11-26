<?php

namespace App\Notifications;

use App\Models\RepositoryConnection;
use App\Models\User;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Alerts the project owner when a repository connection breaks and stops automatic syncing.
 */
class RepositoryConnectionBrokenNotification extends Notification
{
    public int $connectionId;

    public int $projectId;

    public string $repository;

    public string $reason;

    public string $settingsUrl;

    public function __construct(RepositoryConnection $connection, string $reason)
    {
        $this->connectionId = $connection->id;
        $this->projectId = $connection->project_id;
        $this->repository = $connection->repository;
        $this->reason = $reason;
        $this->settingsUrl = route('project.settings', ['project' => $connection->project_id]);
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

        return $channels;
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->reason)
            ->icon('/apple-touch-icon.png')
            ->data(['url' => $this->settingsUrl]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'repository_connection_broken',
            'title' => $this->title(),
            'summary' => $this->reason,
            'repository' => $this->repository,
            'reason' => $this->reason,
            'connection_id' => $this->connectionId,
            'project_id' => $this->projectId,
            'url' => $this->settingsUrl,
        ];
    }

    private function title(): string
    {
        return "Repository sync broken: {$this->repository}";
    }
}
