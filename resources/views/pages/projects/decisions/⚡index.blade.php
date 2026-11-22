<?php

use App\Actions\Decisions\CreateDecisionAction;
use App\Enums\DecisionStatus;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Forms\DecisionForm;
use App\Models\Decision;
use App\Models\Project;
use App\Support\QueryHelper;
use Illuminate\Database\Eloquent\Builder;
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

    public string $search = '';

    public function mount(Project $project): void
    {
        $this->authorize('viewAny', [Decision::class, $project]);

        $this->project = $project;
        $this->form->setProject($project);
    }

    public function selectStatus(string $status = ''): void
    {
        if ($status === '' || DecisionStatus::tryFrom($status) !== null) {
            $this->statusFilter = $status;
        }
    }

    public function resetFilters(): void
    {
        $this->statusFilter = '';
        $this->search = '';
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

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $status = DecisionStatus::tryFrom($this->statusFilter);

        $countsByStatus = $this->project->decisions()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalCount = (int) $countsByStatus->sum();

        $statusChips = collect(DecisionStatus::cases())
            ->map(fn (DecisionStatus $case): array => [
                'status' => $case,
                'count' => (int) $countsByStatus->get($case->value, 0),
            ])
            ->filter(fn (array $chip): bool => $chip['count'] > 0)
            ->values();

        $query = $this->project->decisions()
            ->with('supersededBy')
            ->orderByDesc('number');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $trimmedSearch = trim($this->search);

        if ($trimmedSearch !== '') {
            $like = '%'.QueryHelper::escapeLike($trimmedSearch).'%';

            $query->where(function (Builder $searchQuery) use ($like): void {
                $searchQuery->whereRaw("title LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("context LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("decision LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("consequences LIKE ? ESCAPE '!'", [$like]);
            });
        }

        return [
            'decisions' => $query->get(),
            'totalCount' => $totalCount,
            'statusChips' => $statusChips,
            'supersedingOptions' => $this->project->decisions()
                ->orderByDesc('number')
                ->get(['id', 'number', 'title']),
            'statuses' => DecisionStatus::cases(),
        ];
    }
};
?>

<div class="max-w-7xl mx-auto w-full py-2 sm:py-4 space-y-6">
    <header class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
        <div class="min-w-0 shrink-0">
            <div class="flex items-center gap-2.5">
                <h1 class="font-headline font-bold text-2xl sm:text-3xl tracking-tight text-ink">
                    {{ __('Decisions') }}
                </h1>
                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold text-ink-muted bg-surface-high/70 rounded-full tabular-nums">
                    {{ $totalCount }}
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">
                {{ __('Why the project is built the way it is.') }}
            </p>
        </div>

        <div class="flex flex-wrap xl:flex-nowrap items-center gap-3 min-w-0">
            @if ($totalCount > 0)
                <nav
                    data-status-filters
                    aria-label="Filter decisions by status"
                    class="flex flex-wrap items-center gap-1 bg-surface-low p-1 rounded-xl border border-ghost"
                >
                    @php
                        $isAllActive = $statusFilter === '';
                    @endphp
                    <button
                        type="button"
                        data-status-chip="all"
                        wire:click="selectStatus('')"
                        aria-pressed="{{ $isAllActive ? 'true' : 'false' }}"
                        @class([
                            'inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-1.5 max-md:min-h-11 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                            'bg-ink text-surface-card shadow-xs dark:bg-zinc-100 dark:text-zinc-900' => $isAllActive,
                            'text-ink-muted hover:text-ink' => ! $isAllActive,
                        ])
                    >
                        <span>{{ __('All') }}</span>
                        <span class="tabular-nums opacity-75">{{ $totalCount }}</span>
                    </button>

                    @foreach ($statusChips as $chip)
                        @php
                            $chipStatus = $chip['status'];
                            $isChipActive = $statusFilter === $chipStatus->value;
                        @endphp
                        <button
                            type="button"
                            wire:key="status-chip-{{ $chipStatus->value }}"
                            data-status-chip="{{ $chipStatus->value }}"
                            wire:click="selectStatus('{{ $chipStatus->value }}')"
                            aria-pressed="{{ $isChipActive ? 'true' : 'false' }}"
                            @class([
                                'inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-1.5 max-md:min-h-11 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                                'bg-ink text-surface-card shadow-xs dark:bg-zinc-100 dark:text-zinc-900' => $isChipActive,
                                'text-ink-muted hover:text-ink' => ! $isChipActive,
                            ])
                        >
                            <span>{{ $chipStatus->label() }}</span>
                            <span class="tabular-nums opacity-75">{{ $chip['count'] }}</span>
                        </button>
                    @endforeach
                </nav>
            @endif

            <div class="w-full sm:w-56">
                <label for="decisions-search" class="sr-only">{{ __('Search decisions') }}</label>
                <flux:input
                    id="decisions-search"
                    wire:model.live="search"
                    icon="magnifying-glass"
                    placeholder="{{ __('Search decisions') }}"
                    aria-label="{{ __('Search decisions') }}"
                    clearable
                />
            </div>

            <flux:modal.trigger name="create-decision">
                <flux:button variant="primary" icon="plus" class="whitespace-nowrap shrink-0 max-md:min-h-11">
                    {{ __('New decision') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </header>

    @if ($totalCount === 0)
        <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient text-center px-6 py-16 space-y-4">
            <div class="size-12 rounded-2xl bg-surface-low flex items-center justify-center mx-auto text-ink-subtle">
                <flux:icon.scale class="size-6" />
            </div>

            <div class="space-y-1.5 max-w-md mx-auto">
                <h2 class="font-headline font-bold text-lg text-ink">
                    {{ __('Record why the project is built this way') }}
                </h2>
                <p class="text-sm text-ink-muted">
                    {{ __('Keep architectural choices, trade-offs, and context together so future decisions stay grounded.') }}
                </p>
            </div>

            <div class="pt-2 flex flex-col items-center gap-3">
                <flux:modal.trigger name="create-decision">
                    <flux:button variant="primary" icon="plus" class="whitespace-nowrap max-md:min-h-11">
                        {{ __('New decision') }}
                    </flux:button>
                </flux:modal.trigger>

                <p class="text-xs text-ink-subtle">
                    {{ __('or ask your agent to log one with') }}
                    <code class="px-1.5 py-0.5 rounded bg-surface-low text-ink-muted font-mono">log_decision</code>
                </p>
            </div>
        </div>
    @elseif ($decisions->isEmpty())
        <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient text-center px-6 py-14 space-y-3">
            <div class="size-11 rounded-2xl bg-surface-low flex items-center justify-center mx-auto text-ink-subtle">
                <flux:icon.magnifying-glass class="size-5" />
            </div>
            <h2 class="font-headline font-bold text-lg text-ink">{{ __('No matching decisions') }}</h2>
            <p class="text-sm text-ink-muted">
                {{ __('No decisions match the current filter or search.') }}
            </p>
            <div class="pt-1">
                <flux:button variant="outline" size="sm" wire:click="resetFilters" class="max-md:min-h-11 bg-surface-card! border-ghost!">
                    {{ __('Clear filters') }}
                </flux:button>
            </div>
        </div>
    @else
        <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient overflow-hidden py-1">
            @foreach ($decisions as $decision)
                @php
                    $isSuperseded = $decision->status === DecisionStatus::Superseded;
                @endphp
                <div
                    wire:key="decision-{{ $decision->id }}"
                    data-decision-row="{{ $decision->id }}"
                    class="relative flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-4 px-5 sm:px-6 py-4 hover:bg-surface-low transition-colors"
                >
                    <div class="flex items-baseline gap-3 min-w-0 flex-1">
                        <span class="font-mono text-xs font-medium text-ink-subtle tabular-nums shrink-0 w-9">
                            #{{ $decision->number }}
                        </span>

                        <a
                            href="{{ route('project.decisions.show', ['project' => $project, 'decision' => $decision]) }}"
                            wire:navigate
                            @class([
                                'font-medium truncate after:absolute after:inset-0 focus-visible:outline-none focus-visible:underline',
                                'text-ink-muted' => $isSuperseded,
                                'text-ink' => ! $isSuperseded,
                            ])
                        >
                            {{ $decision->title }}
                        </a>
                    </div>

                    @if ($isSuperseded && $decision->supersededBy)
                        <div class="flex items-center sm:justify-end pl-12 sm:pl-0 shrink-0 relative z-10">
                            <a
                                href="{{ route('project.decisions.show', ['project' => $project, 'decision' => $decision->supersededBy]) }}"
                                wire:navigate
                                class="text-xs font-medium text-ink-muted hover:text-ink underline underline-offset-4 decoration-ink-subtle/50 hover:decoration-ink transition-colors py-1"
                            >
                                Superseded by #{{ $decision->supersededBy->number }}
                            </a>
                        </div>
                    @else
                        <div class="flex items-center justify-between sm:justify-end gap-4 sm:gap-8 pl-12 sm:pl-0 shrink-0">
                            <span class="inline-flex items-center gap-2 text-xs font-medium text-ink-muted">
                                <span class="size-2 rounded-full shrink-0 {{ $decision->status->dotClass() }}" aria-hidden="true"></span>
                                <span>{{ $decision->status->label() }}</span>
                            </span>

                            <span class="text-xs text-ink-subtle tabular-nums sm:w-24 sm:text-right">
                                {{ $decision->decided_at?->format('M d, Y') ?? '—' }}
                            </span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="create-decision" class="w-[calc(100vw-2rem)] md:max-w-2xl">
        <form wire:submit="createDecision" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New decision') }}</flux:heading>
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
                <flux:button type="submit" variant="primary">{{ __('Create decision') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
