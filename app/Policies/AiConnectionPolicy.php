<?php

namespace App\Policies;

use App\Models\AiConnection;
use App\Models\User;

class AiConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AiConnection $aiConnection): bool
    {
        return $aiConnection->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AiConnection $aiConnection): bool
    {
        return $aiConnection->user_id === $user->id;
    }

    public function delete(User $user, AiConnection $aiConnection): bool
    {
        return $aiConnection->user_id === $user->id;
    }

    public function makeDefault(User $user, AiConnection $aiConnection): bool
    {
        return $aiConnection->user_id === $user->id;
    }
}
