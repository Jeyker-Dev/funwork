<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Livewire\Forms\TaskForm;
use App\Models\Project;
use App\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app', ['title' => 'Task Details'])]
#[Title('Task Details')]
class extends Component {
    public Project $project;

    public Task $task;

    public TaskForm $form;

    public function mount(Project $project, Task $task): void
    {
        $this->authorize('view', $project);
        $this->authorize('view', $task);

        $this->project = $project;
        $this->task = $task;

        $this->form->setTask($task);
    }

    public function save(): void
    {
        $this->authorize('update', $this->task);

        $this->form->update($this->task);
        $this->task->refresh();
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->task);

        $this->task->delete();

        $this->redirect(route('project.tasks', $this->project), navigate: true);
    }
};
?>

@php
    $modalName = 'delete-task-'.$task->id;
@endphp

<div class="max-w-4xl mx-auto w-full py-6 space-y-6">
    <!-- Breadcrumb / Back Navigation -->
    <div>
        <a href="{{ route('project.tasks', ['project' => $project]) }}"
           class="inline-flex items-center gap-2 text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 transition"
           wire:navigate>
            <flux:icon.arrow-left class="size-4" stroke-width="2.5" />
            Back to Tasks
        </a>
    </div>

    <!-- Task Overview Card -->
    <flux:card class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <flux:badge :color="$task->status->fluxColor()" class="{{ $task->status->badge() }}">
                    <span class="inline-block size-2 rounded-full {{ $task->status->dot() }} mr-1.5"></span>
                    {{ $task->status->value }}
                </flux:badge>

                <flux:badge size="sm" class="uppercase font-semibold">
                    {{ $task->priority->value }}
                </flux:badge>
            </div>

            <div class="flex items-center gap-4 text-sm text-zinc-500 dark:text-zinc-400">
                @if($task->due_date)
                    <div class="flex items-center gap-1.5">
                        <flux:icon.calendar-days class="size-4" />
                        <span>Due {{ $task->due_date->format('M d, Y') }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div>
            <flux:heading size="xl" level="1">{{ $task->title }}</flux:heading>
            @if($task->description)
                <flux:text class="mt-3 whitespace-pre-line text-zinc-600 dark:text-zinc-300">
                    {{ $task->description }}
                </flux:text>
            @endif
        </div>

        @if($task->users->isNotEmpty())
            <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <flux:subheading size="sm" class="mb-2">Assigned Users</flux:subheading>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach($task->users as $assignedUser)
                        <div class="inline-flex items-center gap-2 bg-zinc-100 dark:bg-zinc-800 rounded-full px-3 py-1 text-xs font-medium text-zinc-700 dark:text-zinc-300">
                            <flux:avatar size="xs" :name="$assignedUser->display_name" :initials="$assignedUser->initials()" :src="$assignedUser->avatar_url" />
                            <span>{{ $assignedUser->display_name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </flux:card>

    <!-- Edit Task Form Card -->
    <flux:card>
        <div class="mb-6">
            <flux:heading size="lg">Edit Task</flux:heading>
            <flux:subheading>Update task details and assignments below.</flux:subheading>
        </div>

        <form wire:submit.prevent="save" class="space-y-6">
            <flux:input label="Title" placeholder="Task title" wire:model="form.title" required />

            <flux:textarea label="Description" placeholder="Add task description..." wire:model="form.description" rows="5" required />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:select variant="listbox" label="Status" placeholder="Choose status..." wire:model="form.status">
                    <flux:select.option value="{{ TaskStatus::Todo->value }}">{{ TaskStatus::Todo->value }}</flux:select.option>
                    <flux:select.option value="{{ TaskStatus::Doing->value }}">{{ TaskStatus::Doing->value }}</flux:select.option>
                    <flux:select.option value="{{ TaskStatus::Done->value }}">{{ TaskStatus::Done->value }}</flux:select.option>
                </flux:select>

                <flux:select variant="listbox" label="Priority" placeholder="Choose priority..." wire:model="form.priority">
                    <flux:select.option value="{{ TaskPriority::Low->value }}">{{ TaskPriority::Low->value }}</flux:select.option>
                    <flux:select.option value="{{ TaskPriority::Medium->value }}">{{ TaskPriority::Medium->value }}</flux:select.option>
                    <flux:select.option value="{{ TaskPriority::High->value }}">{{ TaskPriority::High->value }}</flux:select.option>
                </flux:select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input label="Due date" type="date" wire:model="form.dueDate" />

                <flux:select variant="listbox" multiple label="Assigned Users" placeholder="Choose users..." wire:model="form.assignedUserIds">
                    <flux:select.option value="{{ auth()->id() }}">{{ auth()->user()->display_name }}</flux:select.option>
                </flux:select>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <flux:modal.trigger name="{{ $modalName }}">
                    <flux:button type="button" variant="danger">Delete task</flux:button>
                </flux:modal.trigger>

                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:card>

    <!-- Delete Task Confirmation Modal -->
    <flux:modal name="{{ $modalName }}" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete Task</flux:heading>
                <flux:text class="mt-2">
                    Are you sure you want to delete this task? This action cannot be undone.
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

    @persist('toast')
        <flux:toast />
    @endpersist
</div>