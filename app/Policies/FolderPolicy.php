<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\Project;
use App\Models\User;

class FolderPolicy
{
    public function view(User $user, Folder $folder): bool
    {
        return $folder->project !== null && $folder->project->owner_id === $user->id;
    }

    public function create(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }

    public function update(User $user, Folder $folder): bool
    {
        return $folder->project !== null && $folder->project->owner_id === $user->id;
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $folder->project !== null && $folder->project->owner_id === $user->id;
    }
}
