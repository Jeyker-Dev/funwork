<?php

namespace App\Livewire\Forms;

use App\Actions\Decisions\CreateDecisionAction;
use App\Actions\Decisions\UpdateDecisionAction;
use App\Data\Decisions\DecisionData;
use App\Enums\DecisionStatus;
use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class DecisionForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $title = '';

    public string $status = DecisionStatus::Proposed->value;

    #[Validate('required|string')]
    public string $context = '';

    #[Validate('required|string')]
    public string $decision = '';

    #[Validate('required|string')]
    public string $consequences = '';

    #[Validate('nullable|date_format:Y-m-d')]
    public ?string $decidedAt = null;

    public ?int $supersededById = null;

    public ?int $projectId = null;

    public ?int $decisionId = null;

    protected function rules(): array
    {
        $supersededByRules = [
            Rule::requiredIf(fn (): bool => $this->status === DecisionStatus::Superseded->value),
            'nullable',
            'integer',
            Rule::exists('decisions', 'id')->where('project_id', $this->projectId),
        ];

        if ($this->decisionId !== null) {
            $supersededByRules[] = Rule::notIn([$this->decisionId]);
        }

        return [
            'status' => ['required', Rule::enum(DecisionStatus::class)],
            'supersededById' => $supersededByRules,
        ];
    }

    public function setProject(Project $project): void
    {
        $this->reset();
        $this->projectId = $project->id;
        $this->status = DecisionStatus::Proposed->value;
    }

    public function setDecision(Decision $decision): void
    {
        $this->projectId = $decision->project_id;
        $this->decisionId = $decision->id;
        $this->title = $decision->title;
        $this->status = $decision->status->value;
        $this->context = $decision->context;
        $this->decision = $decision->decision;
        $this->consequences = $decision->consequences;
        $this->decidedAt = $decision->decided_at?->format('Y-m-d');
        $this->supersededById = $decision->superseded_by_id;
    }

    public function store(Project $project, User $user, CreateDecisionAction $createDecision): void
    {
        $this->projectId = $project->id;
        $this->validate();

        $created = $createDecision($project, $user, $this->toDto());

        $this->setProject($project);

        Flux::toast(
            heading: 'Decision Created',
            text: "{$created->code} has been recorded.",
            variant: 'success',
            duration: 3000,
        );

        Flux::modals()->close();
    }

    public function update(Decision $decision, User $user, UpdateDecisionAction $updateDecision): void
    {
        $this->projectId = $decision->project_id;
        $this->decisionId = $decision->id;
        $this->validate();

        $updated = $updateDecision($decision, $user, $this->toDto());

        Flux::toast(
            heading: 'Decision Updated',
            text: "{$updated->code} has been updated.",
            variant: 'success',
            duration: 3000,
        );

        Flux::modals()->close();
    }

    private function toDto(): DecisionData
    {
        $status = DecisionStatus::from($this->status);

        return new DecisionData(
            title: $this->title,
            status: $status,
            context: $this->context,
            decision: $this->decision,
            consequences: $this->consequences,
            decidedAt: filled($this->decidedAt) ? Carbon::parse($this->decidedAt) : null,
            supersededById: $status === DecisionStatus::Superseded ? $this->supersededById : null,
        );
    }
}
