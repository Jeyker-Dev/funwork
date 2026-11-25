<?php

namespace App\Actions\Projects;

use App\Data\Projects\ProjectData;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateProjectAction
{
    public function __construct(
        private readonly CreateDefaultAreasAction $createDefaultAreas,
    ) {}

    public function __invoke(User $user, ProjectData $input): Project
    {
        Gate::forUser($user)->authorize('create', Project::class);

        return DB::transaction(function () use ($user, $input): Project {
            $project = Project::create([
                'name' => $input->name,
                'description' => filled($input->description) ? trim($input->description) : null,
                'icon' => $input->icon,
                'type' => $input->type,
                'archived_at' => $input->archivedAt ?: null,
                'status' => $input->status,
                'due_at' => $input->dueAt ?: null,
                'owner_id' => $user->id,
            ]);

            ($this->createDefaultAreas)($project);

            return $project->load('areas');
        });
    }
}
