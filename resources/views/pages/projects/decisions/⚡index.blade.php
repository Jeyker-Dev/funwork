<?php

use App\Actions\Decisions\CreateDecisionAction;
use App\Enums\DecisionStatus;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Forms\DecisionForm;
use App\Models\Decision;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')]
#[Title('Decisions')]
class extends Component
{
    public Project $project;

    public DecisionForm $form;

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    public function mount(Project $project): void
    {
        $this->authorize('viewAny', [Decision::class, $project]);

        $this->project = $project;
        $this->form->setProject($project);
    }

    public function createDecision(CreateDecisionAction $createDecision): void
    {
        $this->authorize('create', [Decision::class, $this->project]);

        try {
            $this->form->store($this->project, auth()->user(), $createDecision);
        } catch (BusinessRuleException $exception) {
            $this->addError('form.supersededById', $exception->getMessage());
        }
    }

    public function with(): array
    {
        $status = DecisionStatus::tryFrom($this->statusFilter);

        $allDecisions = $this->project->decisions()
            ->orderByDesc('number')
            ->get();

        $decisions = $status !== null
            ? $allDecisions->where('status', $status)->values()
            : $allDecisions;

        return [
            'decisions' => $decisions,
            'supersedingOptions' => $allDecisions,
            'statuses' => DecisionStatus::cases(),
        ];
    }
};
?>

<div class="max-w-5xl mx-auto w-full py-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Decisions') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Architecture Decision Records (ADRs) for this project.') }}
            </flux:text>
        </div>

        <div class="flex items-center gap-3">
            <div class="w-48">
                <flux:select wire:model.live="statusFilter" aria-label="Filter by status">
                    <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                    @foreach ($statuses as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:modal.trigger name="create-decision">
                <flux:button variant="primary" icon="plus">
                    {{ __('New Decision') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if ($decisions->isEmpty())
        <flux:card class="text-center py-12">
            <flux:icon.scale class="size-12 mx-auto text-zinc-400" />
            <flux:heading size="lg" class="mt-4">{{ __('No decisions found') }}</flux:heading>
            <flux:text class="mt-1">
                {{ $statusFilter !== '' ? __('No decisions match the selected status.') : __('Record your first architecture decision for this project.') }}
            </flux:text>
        </flux:card>
    @else
        <flux:card class="p-0 overflow-hidden">
            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($decisions as $decision)
                    <a
                        wire:key="decision-{{ $decision->id }}"
                        href="{{ route('project.decisions.show', ['project' => $project, 'decision' => $decision]) }}"
                        wire:navigate
                        class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="font-mono text-xs font-semibold text-zinc-500 dark:text-zinc-400 shrink-0">
                                {{ $decision->code }}
                            </span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $decision->title }}
                            </span>
                        </div>

                        <div class="flex items-center gap-4 shrink-0">
                            <flux:badge size="sm" :color="$decision->status->fluxColor()">
                                {{ $decision->status->label() }}
                            </flux:badge>

                            <span class="text-xs text-zinc-500 dark:text-zinc-400 w-24 text-right">
                                {{ $decision->decided_at?->format('M d, Y') ?? '—' }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </flux:card>
    @endif

    <flux:modal name="create-decision" class="md:w-2xl">
        <form wire:submit="createDecision" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New Decision') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Record an architecture decision for this project.') }}</flux:text>
            </div>

            <x-projects.decisions.form-fields
                :statuses="$statuses"
                :superseding-options="$supersedingOptions"
                :current-status="$form->status"
            />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create Decision') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
