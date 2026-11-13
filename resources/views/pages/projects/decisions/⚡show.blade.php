<?php

use App\Actions\Decisions\UpdateDecisionAction;
use App\Enums\DecisionStatus;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Forms\DecisionForm;
use App\Models\Decision;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')]
#[Title('Decision Details')]
class extends Component
{
    public Project $project;

    public Decision $decision;

    public DecisionForm $form;

    public function mount(Project $project, Decision $decision): void
    {
        $this->authorize('view', $project);
        $this->authorize('view', $decision);

        $this->project = $project;
        $this->decision = $decision->load(['supersededBy', 'creator']);
        $this->form->setDecision($this->decision);
    }

    public function save(UpdateDecisionAction $updateDecision): void
    {
        $this->authorize('update', $this->decision);

        try {
            $this->form->update($this->decision, auth()->user(), $updateDecision);
            $this->decision->refresh()->load(['supersededBy', 'creator']);
        } catch (BusinessRuleException $exception) {
            $this->addError('form.supersededById', $exception->getMessage());
        }
    }

    public function with(): array
    {
        return [
            'statuses' => DecisionStatus::cases(),
            'supersedingOptions' => $this->project->decisions()
                ->where('id', '!=', $this->decision->id)
                ->orderByDesc('number')
                ->get(),
        ];
    }
};
?>

<div class="max-w-4xl mx-auto w-full py-6 space-y-6">
    <div class="flex items-center justify-between gap-4">
        <a
            href="{{ route('project.decisions', ['project' => $project]) }}"
            class="inline-flex items-center gap-2 text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 transition"
            wire:navigate
        >
            <flux:icon.arrow-left class="size-4" stroke-width="2.5" />
            {{ __('Back to Decisions') }}
        </a>

        <flux:modal.trigger name="edit-decision">
            <flux:button variant="filled" icon="pencil-square">
                {{ __('Edit') }}
            </flux:button>
        </flux:modal.trigger>
    </div>

    <flux:card class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="font-mono text-sm font-semibold text-zinc-500 dark:text-zinc-400">
                    {{ $decision->code }}
                </span>

                <flux:badge :color="$decision->status->fluxColor()">
                    {{ $decision->status->label() }}
                </flux:badge>
            </div>

            @if ($decision->decided_at)
                <div class="flex items-center gap-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                    <flux:icon.calendar-days class="size-4" />
                    <span>{{ __('Decided') }} {{ $decision->decided_at->format('M d, Y') }}</span>
                </div>
            @endif
        </div>

        @if ($decision->supersededBy)
            <flux:callout variant="warning" icon="arrow-uturn-right">
                <flux:callout.heading>{{ __('Superseded') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('This decision was superseded by') }}
                    <a
                        href="{{ route('project.decisions.show', ['project' => $project, 'decision' => $decision->supersededBy]) }}"
                        class="font-semibold underline hover:no-underline"
                        wire:navigate
                    >
                        {{ $decision->supersededBy->code }}: {{ $decision->supersededBy->title }}
                    </a>.
                </flux:callout.text>
            </flux:callout>
        @endif

        <flux:heading size="xl" level="1">{{ $decision->title }}</flux:heading>

        <div class="space-y-6 pt-2 border-t border-zinc-200 dark:border-zinc-800">
            <section class="space-y-2">
                <flux:heading size="lg" level="2">{{ __('Context') }}</flux:heading>
                <div class="markdown-content">
                    {!! $decision->context_html !!}
                </div>
            </section>

            <section class="space-y-2">
                <flux:heading size="lg" level="2">{{ __('Decision') }}</flux:heading>
                <div class="markdown-content">
                    {!! $decision->decision_html !!}
                </div>
            </section>

            <section class="space-y-2">
                <flux:heading size="lg" level="2">{{ __('Consequences') }}</flux:heading>
                <div class="markdown-content">
                    {!! $decision->consequences_html !!}
                </div>
            </section>
        </div>
    </flux:card>

    <flux:modal name="edit-decision" class="md:w-2xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit') }} {{ $decision->code }}</flux:heading>
                <flux:text class="mt-1">{{ __('Update decision details and status.') }}</flux:text>
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
                <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
