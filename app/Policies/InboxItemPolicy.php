<?php

namespace App\Policies;

use App\Models\InboxItem;
use App\Models\Project;
use App\Models\User;

class InboxItemPolicy
{
    public function view(User $user, InboxItem $inboxItem): bool
    {
        return $this->owns($user, $inboxItem);
    }

    public function create(User $user, ?Project $project = null): bool
    {
        return $project === null || $project->owner_id === $user->id;
    }

    public function update(User $user, InboxItem $inboxItem): bool
    {
        return $this->owns($user, $inboxItem);
    }

    public function delete(User $user, InboxItem $inboxItem): bool
    {
        return $this->owns($user, $inboxItem);
    }

    private function owns(User $user, InboxItem $inboxItem): bool
    {
        if ($inboxItem->user_id !== $user->id) {
            return false;
        }

        if ($inboxItem->project_id !== null) {
            return $inboxItem->project !== null && $inboxItem->project->owner_id === $user->id;
        }

        return true;
    }
}
