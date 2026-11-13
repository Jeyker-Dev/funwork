<?php

namespace App\Actions\Decisions;

use App\Data\Decisions\DecisionData;
use App\Enums\DecisionStatus;
use App\Exceptions\Decisions\InvalidSupersedingDecisionException;
use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateDecisionAction
{
    public function __invoke(Project $project, User $user, DecisionData $input): Decision
    {
        Gate::forUser($user)->authorize('create', [Decision::class, $project]);

        $supersededById = $this->resolveSupersededById($project, $input);

        return DB::transaction(function () use ($project, $user, $input, $supersededById): Decision {
            $maxNumber = Decision::where('project_id', $project->id)
                ->lockForUpdate()
                ->max('number');

            $number = ($maxNumber ? (int) $maxNumber : 0) + 1;

            return Decision::create([
                'project_id' => $project->id,
                'number' => $number,
                'title' => $input->title,
                'status' => $input->status,
                'context' => $input->context,
                'decision' => $input->decision,
                'consequences' => $input->consequences,
                'decided_at' => $input->decidedAt,
                'superseded_by_id' => $supersededById,
                'created_by' => $user->id,
            ]);
        });
    }

    private function resolveSupersededById(Project $project, DecisionData $input): ?int
    {
        if ($input->status !== DecisionStatus::Superseded) {
            return null;
        }

        if ($input->supersededById === null) {
            throw InvalidSupersedingDecisionException::missing();
        }

        $superseding = Decision::find($input->supersededById);

        if ($superseding === null || $superseding->project_id !== $project->id) {
            throw InvalidSupersedingDecisionException::differentProject();
        }

        return $superseding->id;
    }
}
