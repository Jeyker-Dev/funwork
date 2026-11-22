<?php

use App\Enums\AreaColor;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Forms\TaskForm;
use App\Models\Project;
use App\Models\Task;
use App\Support\DateHelper;
use App\Support\MarkdownChecklist;
use App\Support\TaskDuePresenter;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    #[Locked]
    public int $taskId;

    public TaskForm $form;

    public bool $editingDescription = false;

    public bool $confirmingDelete = false;

    public bool $deleted = false;

    public function mount(Project $project, int $taskId): void
    {
        $this->authorize('view', $project);

        $task = Task::with(['area', 'users'])
            ->where('project_id', $project->id)
            ->findOrFail($taskId);

        $this->authorize('view', $task);

        $this->project = $project;
        $this->taskId = $task->id;
        $this->form->setTask($task);
    }

    public function save(): void
    {
        $task = $this->loadAuthorizedTask('update');
        $previousStatus = $task->status;

        $this->form->update($task);
        $task->refresh()->load('area', 'users');
        $this->form->setTask($task);
        $this->editingDescription = false;

        $announcement = $previousStatus !== $task->status
            ? "Moved \"{$task->title}\" to {$task->status->value}."
            : null;

        $this->dispatch('task-updated', announcement: $announcement);
    }

    public function toggleChecklistItem(int $index): void
    {
        $task = $this->loadAuthorizedTask('update');

        try {
            $updated = MarkdownChecklist::toggle($this->form->description, $index);
        } catch (BusinessRuleException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');

            return;
        }

        $this->form->description = $updated;
        $task->updateDescription($updated);

        $this->dispatch('task-updated');
    }

    public function toggleDescriptionEditor(): void
    {
        $this->editingDescription = ! $this->editingDescription;
    }

    public function requestDelete(): void
    {
        $this->loadAuthorizedTask('delete');
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteTask(): void
    {
        $task = $this->loadAuthorizedTask('delete');
        $title = $task->title;

        $task->delete();
        $this->deleted = true;
        $this->confirmingDelete = false;

        Flux::toast('Task deleted.', variant: 'success');
        $this->dispatch('task-deleted', announcement: "Deleted \"{$title}\".");
    }

    public function close(): void
    {
        $this->dispatch('close-task-panel');
    }

    public function with(): array
    {
        $task = $this->deleted
            ? null
            : Task::with('area')->where('project_id', $this->project->id)->find($this->taskId);

        if ($task === null) {
            return [
                'task' => null,
                'areas' => $this->project->areas,
                'selectedStatus' => TaskStatus::Todo,
                'selectedArea' => null,
                'due' => null,
                'createdLabel' => '—',
                'descriptionHtml' => '',
            ];
        }

        $timezone = DateHelper::userTimezone();
        $localToday = DateHelper::userToday();
        $selectedStatus = TaskStatus::tryFrom($this->form->status) ?? $task->status;
        $selectedArea = $this->form->areaId
            ? $this->project->areas->firstWhere('id', (int) $this->form->areaId)
            : null;
        $due = TaskDuePresenter::fromDateString($this->form->dueDate, $selectedStatus, $localToday);

        return [
            'task' => $task,
            'areas' => $this->project->areas,
            'selectedStatus' => $selectedStatus,
            'selectedArea' => $selectedArea,
            'due' => $due,
            'createdLabel' => $task->created_at?->timezone($timezone)->format('M j') ?? '—',
            'descriptionHtml' => MarkdownChecklist::toInteractiveHtml($this->form->description),
        ];
    }

    private function loadAuthorizedTask(string $ability): Task
    {
        $task = Task::with(['area', 'users'])
            ->where('project_id', $this->project->id)
            ->findOrFail($this->taskId);

        $this->authorize($ability, $task);

        return $task;
    }
};
?>

<div>
@if($task !== null)
<div
    x-data
    x-init="$nextTick(() => $refs.panelTitle?.focus())"
    x-on:keydown.escape.window="if ($wire.confirmingDelete) { $wire.cancelDelete() } else { $wire.close() }"
    class="fixed inset-0 z-50 flex justify-end"
    role="dialog"
    aria-modal="true"
    aria-labelledby="task-panel-heading-{{ $taskId }}"
>
    <div
        wire:click="close"
        aria-hidden="true"
        class="fixed inset-0 bg-zinc-950/20 dark:bg-black/60 backdrop-blur-[2px] transition-opacity"
    ></div>

    <aside class="relative z-10 w-full md:w-[480px] h-full bg-surface-card border-l border-ghost shadow-2xl flex flex-col justify-between overflow-hidden">
        <form wire:submit.prevent="save" class="flex flex-col h-full min-h-0">
            <div class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-6 tasks-scroll">
                {{-- Top bar: Status selector + Close --}}
                <div class="flex items-center justify-between gap-3">
                    <div class="inline-flex items-center gap-2 rounded-full bg-surface-low px-3 py-1.5 border border-ghost">
                        <span class="size-2 rounded-full shrink-0 {{ $selectedStatus->dot() }}" aria-hidden="true"></span>
                        <label for="panel-status-{{ $taskId }}" class="sr-only">Status</label>
                        <select
                            id="panel-status-{{ $taskId }}"
                            wire:model.live="form.status"
                            class="bg-transparent text-xs font-medium text-ink focus:outline-none cursor-pointer pr-1"
                        >
                            @foreach(TaskStatus::cases() as $statusOption)
                                <option value="{{ $statusOption->value }}" class="bg-surface-card text-ink">
                                    {{ $statusOption->value }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button
                        type="button"
                        wire:click="close"
                        aria-label="Close panel"
                        class="inline-flex items-center justify-center size-9 max-md:min-h-11 max-md:min-w-11 rounded-lg text-ink-subtle hover:text-ink hover:bg-surface-low transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        <flux:icon name="x-mark" class="size-4" aria-hidden="true" />
                    </button>
                </div>

                {{-- Editable Title --}}
                <div class="space-y-1">
                    <label for="task-panel-heading-{{ $taskId }}" class="sr-only">Task title</label>
                    <input
                        id="task-panel-heading-{{ $taskId }}"
                        x-ref="panelTitle"
                        type="text"
                        wire:model="form.title"
                        placeholder="Task title"
                        class="w-full font-headline font-semibold text-xl text-ink leading-snug bg-transparent border border-transparent rounded-lg px-2.5 py-1.5 -mx-2.5 hover:bg-surface-low focus:bg-surface-low focus:border-brand-strong focus:outline-none transition-colors"
                    />
                    <flux:error name="form.title" />
                </div>

                {{-- Properties card --}}
                <div class="bg-surface-low rounded-xl p-4 space-y-3">
                    <div class="grid grid-cols-[96px_1fr] items-center gap-3 text-xs">
                        <label for="panel-area-{{ $taskId }}" class="font-medium text-ink-muted">Area</label>
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="size-2 rounded-full shrink-0 {{ $selectedArea ? AreaColor::dotClassFor($selectedArea->color) : 'bg-zinc-400' }}" aria-hidden="true"></span>
                            <select
                                id="panel-area-{{ $taskId }}"
                                wire:model.live="form.areaId"
                                class="w-full bg-transparent text-xs font-medium text-ink rounded-md py-1 px-1.5 -ml-1.5 hover:bg-surface-high/60 focus:bg-surface-card focus:outline-none focus:ring-2 focus:ring-brand cursor-pointer"
                            >
                                <option value="" class="bg-surface-card text-ink">No area</option>
                                @foreach($areas as $areaOption)
                                    <option value="{{ $areaOption->id }}" class="bg-surface-card text-ink">
                                        {{ $areaOption->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-[96px_1fr] items-center gap-3 text-xs">
                        <label for="panel-priority-{{ $taskId }}" class="font-medium text-ink-muted">Priority</label>
                        <div>
                            <select
                                id="panel-priority-{{ $taskId }}"
                                wire:model.live="form.priority"
                                @class([
                                    'text-xs font-medium rounded-md py-1 px-2 -ml-2 focus:outline-none focus:ring-2 focus:ring-brand cursor-pointer',
                                    'bg-danger-tint text-danger dark:bg-red-950/50 dark:text-red-400' => $form->priority === TaskPriority::High->value,
                                    'bg-transparent text-ink hover:bg-surface-high/60 focus:bg-surface-card' => $form->priority !== TaskPriority::High->value,
                                ])
                            >
                                @foreach(TaskPriority::cases() as $priorityOption)
                                    <option value="{{ $priorityOption->value }}" class="bg-surface-card text-ink">
                                        {{ $priorityOption->value }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-[96px_1fr] items-center gap-3 text-xs">
                        <label for="panel-due-{{ $taskId }}" class="font-medium text-ink-muted">Due date</label>
                        <div class="flex flex-wrap items-center gap-2 min-w-0">
                            <input
                                id="panel-due-{{ $taskId }}"
                                type="date"
                                wire:model.live="form.dueDate"
                                class="bg-surface-card border border-ghost rounded-lg px-2 py-1 text-xs font-medium text-ink focus:outline-none focus:border-brand-strong"
                            />
                            @if($due)
                                <span @class([
                                    'text-xs font-medium',
                                    'text-danger dark:text-red-400' => $due->isOverdue,
                                    'text-ink-muted' => ! $due->isOverdue,
                                ])>
                                    {{ $due->panelLabel }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-[96px_1fr] items-center gap-3 text-xs">
                        <span class="font-medium text-ink-muted">Created</span>
                        <span class="font-medium text-ink">{{ $createdLabel }}</span>
                    </div>
                </div>

                {{-- Description (Markdown + Checklists) --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="font-headline font-semibold text-xs text-ink-muted">Description</h3>

                        <button
                            type="button"
                            wire:click="toggleDescriptionEditor"
                            class="text-xs font-medium text-accent-content hover:underline cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded px-1.5 py-0.5"
                        >
                            {{ $editingDescription ? 'Preview' : 'Edit' }}
                        </button>
                    </div>

                    @if($editingDescription)
                        <div class="space-y-1.5">
                            <label for="panel-description-{{ $taskId }}" class="sr-only">Description</label>
                            <textarea
                                id="panel-description-{{ $taskId }}"
                                wire:model="form.description"
                                rows="7"
                                placeholder="Write markdown or checklist items (- [ ] ...)"
                                class="w-full rounded-xl bg-surface-low border border-ghost p-3 text-sm text-ink leading-relaxed focus:bg-surface-card focus:border-brand-strong focus:outline-none"
                            ></textarea>
                            <flux:error name="form.description" />
                        </div>
                    @else
                        @if(filled($form->description))
                            <div class="markdown-content text-sm leading-relaxed">
                                {!! $descriptionHtml !!}
                            </div>
                        @else
                            <p class="text-sm text-ink-subtle">No description yet.</p>
                        @endif
                        <flux:error name="form.description" />
                    @endif
                </div>
            </div>

            {{-- Footer --}}
            <div class="border-t border-ghost bg-surface-card px-5 sm:px-6 py-4 shrink-0">
                @if($confirmingDelete)
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs font-medium text-ink-muted">
                            Delete this task? This cannot be undone.
                        </p>

                        <div class="flex items-center gap-2 ml-auto">
                            <button
                                type="button"
                                wire:click="cancelDelete"
                                class="px-3 py-2 max-md:min-h-11 text-xs font-medium text-ink-muted hover:text-ink hover:bg-surface-low rounded-lg transition-colors cursor-pointer"
                            >
                                Cancel
                            </button>

                            <flux:button
                                type="button"
                                variant="danger"
                                size="sm"
                                wire:click="deleteTask"
                                class="max-md:min-h-11"
                            >
                                Confirm delete
                            </flux:button>
                        </div>
                    </div>
                @else
                    <div class="flex items-center justify-between gap-3">
                        <button
                            type="button"
                            wire:click="requestDelete"
                            class="px-3 py-2 max-md:min-h-11 text-xs font-medium text-ink-muted hover:text-ink hover:bg-surface-low rounded-lg transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            Delete task
                        </button>

                        <flux:button type="submit" variant="primary" size="sm" class="max-md:min-h-11 px-5">
                            Save
                        </flux:button>
                    </div>
                @endif
            </div>
        </form>
    </aside>
</div>
@endif
</div>
