<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\User;

class RepositoryConnectionPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }

    public function view(User $user, RepositoryConnection $repositoryConnection): bool
    {
        return $repositoryConnection->project->owner_id === $user->id;
    }

    public function create(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }

    public function update(User $user, RepositoryConnection $repositoryConnection): bool
    {
        return $repositoryConnection->project->owner_id === $user->id;
    }

    public function delete(User $user, RepositoryConnection $repositoryConnection): bool
    {
        return $repositoryConnection->project->owner_id === $user->id;
    }
}
