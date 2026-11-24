<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;

class ResolveQuickCaptureProjectAction
{
    public function __construct(
        private readonly ResolveContinueProjectAction $resolveContinueProject,
    ) {}

    /**
     * Resolve the destination project for global quick capture.
     */
    public function __invoke(User $user, ?Project $routeProject = null, mixed $lastProjectId = null): ?Project
    {
        if ($routeProject !== null) {
            return $routeProject;
        }

        if (is_numeric($lastProjectId)) {
            $lastVisitedProject = Project::find((int) $lastProjectId);

            if ($lastVisitedProject !== null && $user->can('view', $lastVisitedProject)) {
                return $lastVisitedProject;
            }
        }

        $projects = Project::query()
            ->where('owner_id', $user->id)
            ->withTaskCounts()
            ->withMax('tasks', 'updated_at')
            ->withMax('notebooks', 'updated_at')
            ->withMax('files', 'updated_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return ($this->resolveContinueProject)($projects)?->project;
    }
}
