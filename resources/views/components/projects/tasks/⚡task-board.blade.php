<?php

use App\Actions\Tasks\MoveTaskAction;
use App\Enums\AreaColor;
use App\Enums\TaskStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\Task;
use App\Support\DateHelper;
use App\Support\QueryHelper;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    public Project $project;

    #[Locked]
    public int $projectId;

    #[Url]
    public string $area = 'all';

    #[Url(except: 'board')]
    public string $view = 'board';

    public string $search = '';

    public ?int $selectedTaskId = null;

    public string $activeMobileStatus = TaskStatus::Todo->value;

    public int $boardRevision = 0;

    public string $announcement = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->projectId = $project->id;

        if (! in_array($this->view, ['board', 'list'], true)) {
            $this->view = 'board';
        }
    }

    public function setArea(string $area): void
    {
        $this->area = $area;
        $this->dispatch('area-filter-changed', area: $area);
    }

    public function setView(string $view): void
    {
        if (! in_array($view, ['board', 'list'], true)) {
            return;
        }

        $this->view = $view;
    }

    public function setMobileStatus(string $status): void
    {
        if (TaskStatus::tryFrom($status) === null) {
            return;
        }

        $this->activeMobileStatus = $status;
    }

    public function openCreateModal(string $status = TaskStatus::Todo->value): void
    {
        $this->authorize('create', [Task::class, $this->project]);

        $resolved = TaskStatus::tryFrom($status)?->value ?? TaskStatus::Todo->value;
        $this->dispatch('open-create-task', status: $resolved);
    }

    public function openTask(int $taskId): void
    {
        $task = Task::where('project_id', $this->projectId)->findOrFail($taskId);
        $this->authorize('view', $task);

        $this->selectedTaskId = $task->id;
    }

    public function closePanel(): void
    {
        $this->selectedTaskId = null;
    }

    public function moveTask(int $taskId, int $position, string $targetStatus, MoveTaskAction $moveTask): void
    {
        $task = Task::where('project_id', $this->projectId)->findOrFail($taskId);
        $this->authorize('update', $task);

        $statusEnum = TaskStatus::tryFrom($targetStatus);

        if ($statusEnum === null) {
            Flux::toast('Could not move task: invalid status.', variant: 'danger');
            $this->boardRevision++;

            return;
        }

        try {
            $moveTask($task, $statusEnum, $position, $this->area);
        } catch (BusinessRuleException|ValidationException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
            $this->boardRevision++;

            return;
        }

        $this->announcement = "Moved \"{$task->title}\" to {$statusEnum->value}.";
    }

    #[On('search-updated')]
    public function updateSearch(string $search = ''): void
    {
        $this->search = trim($search);
    }

    #[On('task-created')]
    #[On('task-updated')]
    public function refreshBoard(?string $announcement = null): void
    {
        if ($announcement !== null) {
            $this->announcement = $announcement;
        }
    }

    #[On('task-deleted')]
    public function handleTaskDeleted(?string $announcement = null): void
    {
        $this->selectedTaskId = null;

        if ($announcement !== null) {
            $this->announcement = $announcement;
        }
    }

    #[On('close-task-panel')]
    public function handleClosePanel(): void
    {
        $this->selectedTaskId = null;
    }

    #[On('areas-updated')]
    public function onAreasUpdated(): void
    {
        $this->project->refresh();
    }

    public function with(): array
    {
        $query = Task::query()
            ->with('area')
            ->where('project_id', $this->projectId)
            ->orderBy('position')
            ->orderBy('id');

        QueryHelper::applyAreaFilter($query, $this->area);

        if ($this->search !== '') {
            $escaped = QueryHelper::escapeLike($this->search);
            $like = "%{$escaped}%";

            $query->where(function (Builder $searchQuery) use ($like): void {
                $searchQuery->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        $tasks = $query->get();
        $grouped = $tasks->groupBy(fn (Task $task): string => $task->status->value);

        $tasksByStatus = [];
        foreach (TaskStatus::cases() as $status) {
            $tasksByStatus[$status->value] = $grouped->get($status->value, collect());
        }

        return [
            'tasks' => $tasks,
            'tasksByStatus' => $tasksByStatus,
            'totalCount' => $tasks->count(),
            'areas' => $this->project->areas,
            'localToday' => DateHelper::userToday(),
        ];
    }
}; ?>

<div
    class="flex flex-col flex-1 min-h-0 gap-4"
    x-data="{
        lastDrag: null,
        _cleanup: null,
        captureDragStart(event) {
            const item = event.target.closest('[wire\\:sort\\:item]');
            if (!item || !item.parentElement) return;
            this.lastDrag = {
                item,
                from: item.parentElement,
                nextSibling: item.nextElementSibling,
            };
        },
        init() {
            if (window.Livewire?.interceptMessage) {
                this._cleanup = window.Livewire.interceptMessage(({ message, onError, onFailure }) => {
                    if (message.component?.id !== $wire.__instance?.id) return;
                    const isMove = Array.from(message.actions || []).some((action) => action.name === 'moveTask');
                    if (!isMove) return;

                    const revert = () => {
                        if (this.lastDrag?.item && this.lastDrag?.from) {
                            const { item, from, nextSibling } = this.lastDrag;
                            if (nextSibling && nextSibling.parentElement === from) {
                                from.insertBefore(item, nextSibling);
                            } else {
                                from.appendChild(item);
                            }
                            this.lastDrag = null;
                        }
                        if (window.Flux?.toast) {
                            window.Flux.toast({ text: 'Could not move task. Reverted to previous position.', variant: 'danger' });
                        }
                    };

                    onError(({ preventDefault }) => {
                        preventDefault();
                        revert();
                    });
                    onFailure(() => revert());
                });
            }
        },
        destroy() {
            this._cleanup?.();
        }
    }"
    x-on:pointerdown="captureDragStart($event)"
>
    <div role="status" aria-live="polite" class="sr-only">
        {{ $announcement }}
    </div>

    {{-- S1: Compact single-row header --}}
    <header class="flex flex-wrap items-center justify-between gap-3 shrink-0">
        <div class="flex flex-wrap items-center gap-3 min-w-0">
            <div class="flex items-center gap-2.5">
                <h1 class="font-headline font-bold text-2xl sm:text-3xl tracking-tight text-ink">
                    Tasks
                </h1>
                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold text-ink-muted bg-surface-high/70 rounded-full tabular-nums">
                    {{ $totalCount }}
                </span>
            </div>

            <nav aria-label="Filter tasks by area" class="flex flex-wrap items-center gap-1.5">
                @php
                    $isAllActive = $area === 'all' || $area === '';
                    $isNoAreaActive = in_array(strtolower($area), ['no-area', 'none', 'no_area', 'no area', '0'], true);
                @endphp

                <button
                    type="button"
                    wire:click="setArea('all')"
                    aria-pressed="{{ $isAllActive ? 'true' : 'false' }}"
                    @class([
                        'inline-flex items-center px-3 py-1.5 max-md:min-h-11 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                        'bg-ink text-surface-card shadow-xs dark:bg-zinc-100 dark:text-zinc-900' => $isAllActive,
                        'bg-surface-card text-ink-muted hover:text-ink border border-ghost' => ! $isAllActive,
                    ])
                >
                    All
                </button>

                @foreach($areas as $projectArea)
                    @php
                        $isAreaActive = $area === $projectArea->slug;
                    @endphp
                    <button
                        type="button"
                        wire:key="area-chip-{{ $projectArea->id }}"
                        wire:click="setArea('{{ $projectArea->slug }}')"
                        aria-pressed="{{ $isAreaActive ? 'true' : 'false' }}"
                        @class([
                            'inline-flex items-center gap-1.5 px-3 py-1.5 max-md:min-h-11 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                            'bg-ink text-surface-card shadow-xs dark:bg-zinc-100 dark:text-zinc-900' => $isAreaActive,
                            'bg-surface-card text-ink-muted hover:text-ink border border-ghost' => ! $isAreaActive,
                        ])
                    >
                        <span class="size-1.5 rounded-full shrink-0 {{ AreaColor::dotClassFor($projectArea->color) }}" aria-hidden="true"></span>
                        <span>{{ $projectArea->name }}</span>
                    </button>
                @endforeach

                <button
                    type="button"
                    wire:click="setArea('no-area')"
                    aria-pressed="{{ $isNoAreaActive ? 'true' : 'false' }}"
                    @class([
                        'inline-flex items-center gap-1.5 px-3 py-1.5 max-md:min-h-11 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                        'bg-ink text-surface-card shadow-xs dark:bg-zinc-100 dark:text-zinc-900' => $isNoAreaActive,
                        'bg-surface-card text-ink-muted hover:text-ink border border-ghost' => ! $isNoAreaActive,
                    ])
                >
                    <span class="size-1.5 rounded-full shrink-0 bg-zinc-400" aria-hidden="true"></span>
                    <span>No area</span>
                </button>
            </nav>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <div class="inline-flex items-center bg-surface-high/70 p-0.5 rounded-lg" role="group" aria-label="Task view mode">
                <button
                    type="button"
                    wire:click="setView('board')"
                    aria-pressed="{{ $view === 'board' ? 'true' : 'false' }}"
                    @class([
                        'inline-flex items-center gap-1.5 px-2.5 py-1.5 max-md:min-h-10 rounded-md text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                        'bg-surface-card text-ink shadow-xs' => $view === 'board',
                        'text-ink-muted hover:text-ink' => $view !== 'board',
                    ])
                >
                    <flux:icon name="view-columns" class="size-3.5" aria-hidden="true" />
                    <span>Board</span>
                </button>

                <button
                    type="button"
                    wire:click="setView('list')"
                    aria-pressed="{{ $view === 'list' ? 'true' : 'false' }}"
                    @class([
                        'inline-flex items-center gap-1.5 px-2.5 py-1.5 max-md:min-h-10 rounded-md text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                        'bg-surface-card text-ink shadow-xs' => $view === 'list',
                        'text-ink-muted hover:text-ink' => $view !== 'list',
                    ])
                >
                    <flux:icon name="bars-3" class="size-3.5" aria-hidden="true" />
                    <span>List</span>
                </button>
            </div>

            <livewire:projects.tasks.import-tasks :project="$project" />

            <flux:button
                variant="primary"
                size="sm"
                icon="plus"
                wire:click="openCreateModal('Todo')"
                class="max-md:min-h-11"
            >
                New task
            </flux:button>
        </div>
    </header>

    @if($view === 'board')
        {{-- Mobile status switcher (< lg) --}}
        <div class="lg:hidden grid grid-cols-3 gap-1.5 bg-surface-low p-1 rounded-xl" role="tablist" aria-label="Task status columns">
            @foreach(TaskStatus::cases() as $status)
                @php
                    $isMobileActive = $activeMobileStatus === $status->value;
                    $mobileCount = $tasksByStatus[$status->value]->count();
                @endphp
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $isMobileActive ? 'true' : 'false' }}"
                    wire:click="setMobileStatus('{{ $status->value }}')"
                    @class([
                        'inline-flex items-center justify-center gap-1.5 min-h-11 px-2 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                        'bg-surface-card text-ink shadow-xs' => $isMobileActive,
                        'text-ink-muted hover:text-ink' => ! $isMobileActive,
                    ])
                >
                    <span class="size-2 rounded-full shrink-0 {{ $status->dot() }}" aria-hidden="true"></span>
                    <span>{{ $status->value }}</span>
                    <span class="text-ink-subtle tabular-nums">{{ $mobileCount }}</span>
                </button>
            @endforeach
        </div>

        {{-- S2, S3, S4: Neutral columns with independent scroll & wire:sort --}}
        <section
            wire:key="board-columns-{{ $boardRevision }}"
            aria-label="Tasks board"
            class="grid grid-cols-1 lg:grid-cols-3 gap-5 flex-1 lg:flex-none min-h-0 lg:h-[calc(100vh-12.5rem)] lg:min-h-[26rem]"
        >
            @foreach(TaskStatus::cases() as $status)
                @php
                    $statusValue = $status->value;
                    $columnTasks = $tasksByStatus[$statusValue];
                    $columnCount = $columnTasks->count();
                    $isVisibleOnMobile = $activeMobileStatus === $statusValue;
                @endphp

                <div
                    wire:key="column-{{ $statusValue }}"
                    @class([
                        'bg-surface-low rounded-2xl p-3 flex-col min-h-0',
                        'flex' => $isVisibleOnMobile,
                        'hidden lg:flex' => ! $isVisibleOnMobile,
                    ])
                >
                    <div class="flex items-center justify-between pb-3 px-1 shrink-0">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="size-2 rounded-full shrink-0 {{ $status->dot() }}" aria-hidden="true"></span>
                            <h2 class="font-headline font-bold text-sm text-ink">{{ $statusValue }}</h2>
                            <span class="text-xs font-medium text-ink-muted bg-surface-high px-2 py-0.5 rounded-full tabular-nums">
                                {{ $columnCount }}
                            </span>
                        </div>

                        <button
                            type="button"
                            wire:click="openCreateModal('{{ $statusValue }}')"
                            aria-label="Add task to {{ $statusValue }}"
                            title="Add task to {{ $statusValue }}"
                            class="inline-flex items-center justify-center size-7 max-md:min-h-11 max-md:min-w-11 text-ink-subtle hover:text-ink hover:bg-surface-high/70 rounded-lg transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            <flux:icon name="plus" class="size-4" aria-hidden="true" />
                        </button>
                    </div>

                    <div
                        wire:sort.ghost="moveTask"
                        wire:sort:group="tasks"
                        wire:sort:group-id="{{ $statusValue }}"
                        x-sort:config="{ forceFallback: true, fallbackOnBody: true, fallbackClass: 'task-sort-fallback', ghostClass: 'task-sort-ghost', chosenClass: 'task-sort-chosen', dragClass: 'task-sort-drag', animation: 150 }"
                        data-task-column
                        data-status="{{ $statusValue }}"
                        class="flex-1 overflow-y-auto pr-1 space-y-2.5 min-h-24 tasks-scroll"
                    >
                        @foreach($columnTasks as $task)
                            <x-projects.tasks.board-card :task="$task" :local-today="$localToday" />
                        @endforeach

                        @if($columnTasks->isEmpty())
                            <div class="column-empty-hint rounded-xl border border-dashed border-ghost px-4 py-8 text-center">
                                <p class="text-xs font-medium text-ink-subtle">
                                    {{ $status === TaskStatus::Done ? 'No completed tasks yet' : 'No tasks in '.$statusValue }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <button
                        type="button"
                        wire:click="openCreateModal('{{ $statusValue }}')"
                        class="mt-2 w-full text-left px-2.5 py-2 max-md:min-h-11 text-xs font-medium text-ink-muted hover:text-ink hover:bg-surface-high/60 rounded-lg transition-colors inline-flex items-center gap-1.5 shrink-0 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        <flux:icon name="plus" class="size-3.5" aria-hidden="true" />
                        <span>Add task</span>
                    </button>
                </div>
            @endforeach
        </section>
    @else
        <x-projects.tasks.list-table :tasks="$tasks" :local-today="$localToday" />
    @endif

    <livewire:projects.tasks.create-task :project="$project" :hide-trigger="true" :key="'board-create-task-'.$projectId" />

    @if($selectedTaskId !== null)
        <livewire:projects.tasks.task-detail-panel
            :project="$project"
            :task-id="$selectedTaskId"
            :key="'task-panel-'.$selectedTaskId"
        />
    @endif
</div>
