<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;

class ProjectFilePolicy
{
    public function view(User $user, ProjectFile $projectFile): bool
    {
        return $projectFile->project !== null && $projectFile->project->owner_id === $user->id;
    }

    public function create(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }

    public function update(User $user, ProjectFile $projectFile): bool
    {
        return $projectFile->project !== null && $projectFile->project->owner_id === $user->id;
    }

    public function delete(User $user, ProjectFile $projectFile): bool
    {
        return $projectFile->project !== null && $projectFile->project->owner_id === $user->id;
    }
}
