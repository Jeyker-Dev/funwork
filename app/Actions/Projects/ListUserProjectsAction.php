<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;
use App\Support\QueryHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListUserProjectsAction
{
    private const string LIKE_ESCAPE_CHAR = '!';

    /**
     * List the user's projects with task counts preloaded, optionally filtered by name.
     *
     * @return Collection<int, Project>
     */
    public function __invoke(User $user, string $search = ''): Collection
    {
        $escapeChar = self::LIKE_ESCAPE_CHAR;

        return Project::query()
            ->where('owner_id', $user->id)
            ->when(
                $search !== '',
                fn (Builder $query): Builder => $query->whereRaw(
                    "name LIKE ? ESCAPE '{$escapeChar}'",
                    ['%'.QueryHelper::escapeLike($search).'%']
                )
            )
            ->withTaskCounts()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
    }
}
