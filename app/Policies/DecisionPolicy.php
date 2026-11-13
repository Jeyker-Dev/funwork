<?php

namespace App\Policies;

use App\Models\Decision;
use App\Models\Project;
use App\Models\User;

class DecisionPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }

    public function create(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }

    public function view(User $user, Decision $decision): bool
    {
        return $decision->project->owner_id === $user->id;
    }

    public function update(User $user, Decision $decision): bool
    {
        return $decision->project->owner_id === $user->id;
    }
}
