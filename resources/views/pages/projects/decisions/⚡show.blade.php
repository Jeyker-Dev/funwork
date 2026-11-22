<?php

use App\Actions\Decisions\UpdateDecisionAction;
use App\Enums\DecisionStatus;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Forms\DecisionForm;
use App\Models\Decision;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        $this->decision = $decision->load(['supersededBy', 'supersedes', 'creator']);
        $this->form->setDecision($this->decision);
    }

    public function openEdit(): void
    {
        $this->authorize('update', $this->decision);

        $this->form->setDecision($this->decision);
        Flux::modal('edit-decision')->show();
    }

    public function openSupersede(): void
    {
        $this->authorize('update', $this->decision);

        $this->form->setDecision($this->decision);
        $this->form->status = DecisionStatus::Superseded->value;

        if ($this->form->supersededById === null) {
            $this->form->supersededById = $this->supersedingOptionsQuery()->value('id');
        }

        Flux::modal('edit-decision')->show();
    }

    public function save(UpdateDecisionAction $updateDecision): void
    {
        $this->authorize('update', $this->decision);

        try {
            $this->form->update($this->decision, auth()->user(), $updateDecision);
            $this->decision->refresh()->load(['supersededBy', 'supersedes', 'creator']);
        } catch (BusinessRuleException $exception) {
            $this->addError('form.supersededById', $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'statuses' => DecisionStatus::cases(),
            'supersedingOptions' => $this->supersedingOptionsQuery()->get(),
        ];
    }

    private function supersedingOptionsQuery(): HasMany
    {
        return $this->project->decisions()
            ->where('id', '!=', $this->decision->id)
            ->orderByDesc('number');
    }
};
?>

<div class="max-w-6xl mx-auto w-full py-2 sm:py-4 space-y-6">
    <div>
        <a
            href="{{ route('project.decisions', ['project' => $project]) }}"
            class="inline-flex items-center gap-2 text-sm font-medium text-ink-muted hover:text-ink transition-colors max-md:min-h-11"
            wire:navigate
        >
            <flux:icon.arrow-left class="size-4" stroke-width="2" />
            <span>{{ __('All decisions') }}</span>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-8 lg:items-start">
        @if ($decision->status === DecisionStatus::Superseded && $decision->supersededBy)
            <div
                role="note"
                data-superseded-notice
                class="order-1 lg:order-none lg:col-span-8 lg:col-start-1 lg:row-start-1 flex items-center gap-3 rounded-xl border border-amber-200/80 dark:border-amber-800/50 bg-amber-50/90 dark:bg-amber-950/30 px-4 py-3 text-sm text-amber-950 dark:text-amber-100"
            >
                <flux:icon.information-circle class="size-5 text-amber-700 dark:text-amber-300 shrink-0" />
                <p class="min-w-0">
                    {{ __('This decision was superseded by') }}
                    <a
                        href="{{ route('project.decisions.show', ['project' => $project, 'decision' => $decision->supersededBy]) }}"
                        class="font-semibold underline underline-offset-4 hover:no-underline"
                        wire:navigate
                    >#{{ $decision->supersededBy->number }} {{ $decision->supersededBy->title }}</a>.
                </p>
            </div>
        @endif

        <article @class([
            'contents lg:block lg:col-span-8 lg:col-start-1 bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 sm:p-10 min-w-0',
            'lg:row-start-2' => $decision->status === DecisionStatus::Superseded && $decision->supersededBy,
            'lg:row-start-1' => ! ($decision->status === DecisionStatus::Superseded && $decision->supersededBy),
        ])>
            <header class="order-2 lg:order-none bg-surface-card lg:bg-transparent rounded-2xl lg:rounded-none border border-ghost lg:border-0 p-6 lg:p-0 shadow-ambient lg:shadow-none min-w-0">
                <span class="font-mono text-sm font-medium text-ink-subtle tabular-nums">
                    #{{ $decision->number }}
                </span>

                <h1 class="font-headline font-bold text-2xl sm:text-3xl tracking-tight text-ink mt-1.5 break-words">
                    {{ $decision->title }}
                </h1>
            </header>

            <div class="order-4 lg:order-none lg:mt-8 bg-surface-card lg:bg-transparent rounded-2xl lg:rounded-none border border-ghost lg:border-0 p-6 lg:p-0 shadow-ambient lg:shadow-none space-y-8 max-w-prose min-w-0">
                <section class="space-y-2.5">
                    <h2 class="font-headline font-bold text-lg text-ink">{{ __('Context') }}</h2>
                    <div class="markdown-content">
                        {!! $decision->context_html !!}
                    </div>
                </section>

                <section class="space-y-2.5">
                    <h2 class="font-headline font-bold text-lg text-ink">{{ __('Decision') }}</h2>
                    <div class="markdown-content">
                        {!! $decision->decision_html !!}
                    </div>
                </section>

                <section class="space-y-2.5">
                    <h2 class="font-headline font-bold text-lg text-ink">{{ __('Consequences') }}</h2>
                    <div class="markdown-content">
                        {!! $decision->consequences_html !!}
                    </div>
                </section>
            </div>
        </article>

        <aside class="order-3 lg:order-none lg:col-span-4 lg:col-start-9 lg:row-start-1 lg:row-span-2 lg:sticky lg:top-6 bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 space-y-5 min-w-0">
            <div class="space-y-1.5">
                <span class="block text-xs font-medium text-ink-muted">{{ __('Status') }}</span>
                <div>
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold {{ $decision->status->pillClasses() }}">
                        <span class="size-2 rounded-full shrink-0 {{ $decision->status->dotClass() }}" aria-hidden="true"></span>
                        <span>{{ $decision->status->label() }}</span>
                    </span>
                </div>
            </div>

            <div class="space-y-1">
                <span class="block text-xs font-medium text-ink-muted">{{ __('Decided') }}</span>
                <p class="text-sm font-medium text-ink tabular-nums">
                    {{ $decision->decided_at?->format('M d, Y') ?? '—' }}
                </p>
            </div>

            <div class="space-y-1.5">
                <span class="block text-xs font-medium text-ink-muted">{{ __('Created by') }}</span>
                @if ($decision->creator)
                    @php
                        $author = $decision->creator;
                        $authorAvatar = $author->profile_photo_path ? $author->avatar_url : null;
                    @endphp
                    <div class="flex items-center gap-2.5 min-w-0">
                        <flux:avatar
                            size="xs"
                            circle
                            :name="$author->name"
                            :initials="$author->initials()"
                            :src="$authorAvatar"
                            class="bg-brand-strong! text-white! dark:bg-brand-strong! dark:text-white! shrink-0"
                        />
                        <span class="text-sm font-medium text-ink truncate">
                            {{ $author->name }}
                        </span>
                    </div>
                @else
                    <p class="text-sm font-medium text-ink-subtle">—</p>
                @endif
            </div>

            @if ($decision->supersedes->isNotEmpty() || $decision->supersededBy)
                <div class="space-y-2 pt-3 border-t border-ghost">
                    <span class="block text-xs font-medium text-ink-muted">{{ __('History') }}</span>

                    <div class="space-y-2">
                        @foreach ($decision->supersedes as $prior)
                            <a
                                wire:key="supersedes-{{ $prior->id }}"
                                href="{{ route('project.decisions.show', ['project' => $project, 'decision' => $prior]) }}"
                                wire:navigate
                                class="flex items-start gap-2 text-sm font-medium text-ink hover:text-accent-content transition-colors"
                            >
                                <flux:icon.arrow-uturn-left class="size-4 text-ink-subtle shrink-0 mt-0.5" />
                                <span class="min-w-0 break-words">Supersedes #{{ $prior->number }} {{ $prior->title }}</span>
                            </a>
                        @endforeach

                        @if ($decision->supersededBy)
                            <a
                                href="{{ route('project.decisions.show', ['project' => $project, 'decision' => $decision->supersededBy]) }}"
                                wire:navigate
                                class="flex items-start gap-2 text-sm font-medium text-ink hover:text-accent-content transition-colors"
                            >
                                <flux:icon.arrow-uturn-right class="size-4 text-ink-subtle shrink-0 mt-0.5" />
                                <span class="min-w-0 break-words">Superseded by #{{ $decision->supersededBy->number }} {{ $decision->supersededBy->title }}</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-3 pt-3 border-t border-ghost">
                <flux:button
                    variant="outline"
                    icon="pencil-square"
                    wire:click="openEdit"
                    class="w-full justify-center max-md:min-h-11 bg-surface-card! border-ghost!"
                >
                    {{ __('Edit') }}
                </flux:button>

                <flux:button
                    variant="outline"
                    icon="arrow-uturn-right"
                    wire:click="openSupersede"
                    class="w-full justify-center max-md:min-h-11 bg-surface-card! border-ghost!"
                >
                    {{ __('Supersede') }}
                </flux:button>
            </div>
        </aside>
    </div>

    <flux:modal name="edit-decision" class="w-[calc(100vw-2rem)] md:max-w-2xl">
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
