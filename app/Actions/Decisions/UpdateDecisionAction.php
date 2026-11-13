<?php

namespace App\Actions\Decisions;

use App\Data\Decisions\DecisionData;
use App\Enums\DecisionStatus;
use App\Exceptions\Decisions\InvalidSupersedingDecisionException;
use App\Models\Decision;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateDecisionAction
{
    public function __invoke(Decision $decision, User $user, DecisionData $input): Decision
    {
        Gate::forUser($user)->authorize('update', $decision);

        $supersededById = $this->resolveSupersededById($decision, $input);

        $decision->update([
            'title' => $input->title,
            'status' => $input->status,
            'context' => $input->context,
            'decision' => $input->decision,
            'consequences' => $input->consequences,
            'decided_at' => $input->decidedAt,
            'superseded_by_id' => $supersededById,
        ]);

        return $decision;
    }

    private function resolveSupersededById(Decision $decision, DecisionData $input): ?int
    {
        if ($input->status !== DecisionStatus::Superseded) {
            return null;
        }

        if ($input->supersededById === null) {
            throw InvalidSupersedingDecisionException::missing();
        }

        if ($input->supersededById === $decision->id) {
            throw InvalidSupersedingDecisionException::cannotSupersedeSelf();
        }

        $superseding = Decision::find($input->supersededById);

        if ($superseding === null || $superseding->project_id !== $decision->project_id) {
            throw InvalidSupersedingDecisionException::differentProject();
        }

        return $superseding->id;
    }
}
