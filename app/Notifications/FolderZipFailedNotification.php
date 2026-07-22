<?php

namespace App\Notifications;

use App\Models\Folder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FolderZipFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Folder $folder
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'folder_id' => $this->folder->id,
            'folder_name' => $this->folder->name,
            'message' => "Failed to zip folder {$this->folder->name}.",
        ];
    }
}
