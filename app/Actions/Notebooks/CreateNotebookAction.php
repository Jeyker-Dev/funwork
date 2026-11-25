<?php

namespace App\Actions\Notebooks;

use App\Data\Notebooks\NotebookData;
use App\Models\Area;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CreateNotebookAction
{
    public function __invoke(Project $project, User $user, NotebookData $input, ?Area $area = null): Notebook
    {
        Gate::forUser($user)->authorize('create', [Notebook::class, $project]);

        $notebook = Notebook::create([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'name' => $input->name,
            'description' => filled($input->description) ? trim($input->description) : null,
            'is_archived' => false,
            'is_shared' => false,
            'area_id' => $area?->id,
        ]);

        if ($area !== null) {
            $notebook->setRelation('area', $area);
        }

        return $notebook;
    }
}
