<?php

namespace App\Policies;

use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\User;

class NotebookPagePolicy
{
    public function view(User $user, NotebookPage $page): bool
    {
        return $page->notebook?->project !== null
            && $page->notebook->project->owner_id === $user->id;
    }

    public function create(User $user, Notebook $notebook): bool
    {
        return $notebook->project !== null
            && $notebook->project->owner_id === $user->id;
    }

    public function update(User $user, NotebookPage $page): bool
    {
        return $page->notebook?->project !== null
            && $page->notebook->project->owner_id === $user->id;
    }

    public function delete(User $user, NotebookPage $page): bool
    {
        return $page->notebook?->project !== null
            && $page->notebook->project->owner_id === $user->id;
    }
}
