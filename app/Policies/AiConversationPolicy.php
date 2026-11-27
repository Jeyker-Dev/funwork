<?php

namespace App\Policies;

use App\Models\AiConversation;
use App\Models\Project;
use App\Models\User;

class AiConversationPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('view', $project);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->can('view', $project);
    }

    public function view(User $user, AiConversation $aiConversation): bool
    {
        return $this->ownsWithProjectAccess($user, $aiConversation);
    }

    public function update(User $user, AiConversation $aiConversation): bool
    {
        return $this->ownsWithProjectAccess($user, $aiConversation);
    }

    public function delete(User $user, AiConversation $aiConversation): bool
    {
        return $this->ownsWithProjectAccess($user, $aiConversation);
    }

    public function send(User $user, AiConversation $aiConversation): bool
    {
        return $this->ownsWithProjectAccess($user, $aiConversation);
    }

    private function ownsWithProjectAccess(User $user, AiConversation $aiConversation): bool
    {
        return $aiConversation->user_id === $user->id
            && $aiConversation->project instanceof Project
            && $user->can('view', $aiConversation->project);
    }
}
