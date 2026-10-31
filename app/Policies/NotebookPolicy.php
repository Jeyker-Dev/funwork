<?php

namespace App\Policies;

use App\Models\Notebook;
use App\Models\Project;
use App\Models\User;

class NotebookPolicy
{
    public function view(User $user, Notebook $notebook): bool
    {
        return $notebook->project !== null && $notebook->project->owner_id === $user->id;
    }

    public function create(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }

    public function update(User $user, Notebook $notebook): bool
    {
        return $notebook->project !== null && $notebook->project->owner_id === $user->id;
    }

    public function delete(User $user, Notebook $notebook): bool
    {
        return $notebook->project !== null && $notebook->project->owner_id === $user->id;
    }
}
