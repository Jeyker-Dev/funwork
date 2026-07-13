<?php

use Livewire\Component;
use App\Livewire\Forms\TaskForm;
use App\Models\Task;
use App\Models\User;
use App\Models\Project;

new class extends Component
{
    public TaskForm $form;
    public Project $project;
    public ?int $projectId = null;
    public ?string $status = null;

    public function mount(Project $project, ?string $status = null): void
    {
        $this->project = $project;
        $this->projectId = $project->id;
        $this->status = $status;
    }

    public function with(): array
    {
        return [
            'users' => User::all(),
        ];
    }

    public function save(): void
    {
        $this->form->projectId = $this->projectId;
        $this->form->status = $this->status ?? $this->form->status;

        $this->form->position = Task::where('project_id', $this->projectId)
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->max('position');

        $this->form->position = $this->form->position ? $this->form->position + 1 : 1;

        $this->form->store();

        $this->dispatch('task-created');
    }
};
?>

@php
    $modalName = 'create-task-'.$projectId.'-'.($status ?? 'all');

    $buttonClasses = match ($status) {
        'Todo' => 'border-[rgba(255,128,64,0.48)] bg-[rgba(255,128,64,0.23)] hover:bg-[rgba(255,128,64,0.33)]',
        'Doing' => 'border-[rgba(255,220,70,0.38)] bg-[rgba(255,220,70,0.17)] hover:bg-[rgba(255,220,70,0.27)]',
        'Done' => 'border-[rgba(38,186,112,0.33)] bg-[rgba(38,186,112,0.13)] hover:bg-[rgba(38,186,112,0.23)]',
        default => 'border-gray-200 bg-gray-50 hover:bg-gray-100',
    };

    $iconClasses = match ($status) {
        'Todo' => 'text-orange-500',
        'Doing' => 'text-yellow-500',
        'Done' => 'text-green-500',
        default => 'text-gray-500',
    };
@endphp

<div class="w-full">
    <flux:modal.trigger name="{{ $modalName }}">
        <div class="border-2 border-dashed {{ $buttonClasses }} flex items-center justify-center p-10 cursor-pointer rounded-lg">
            <flux:icon.plus-circle class="{{ $iconClasses }}"/>
        </div>
    </flux:modal.trigger>

    <flux:modal name="{{ $modalName }}" class="w-xl">
        <form wire:submit.prevent="save">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Create Task</flux:heading>
                    <flux:text class="mt-2">
                        Fill out the details below to create a new task for this project.
                    </flux:text>

                </div>

                <flux:input label="Title" placeholder="Review Project Map" wire:model="form.title" />

                <flux:textarea label="Description" placeholder="Add details..." wire:model="form.description" />

                <div class="flex justify-between items-center w-full gap-4 my-6">
                    <div class="w-1/2">
                        <flux:select variant="listbox" label="Status" placeholder="Choose status..." wire:model="form.status">
                            <flux:select.option>Todo</flux:select.option>
                            <flux:select.option>Doing</flux:select.option>
                            <flux:select.option>Done</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="w-1/2">
                        <flux:select variant="listbox" label="Priority" placeholder="Choose priority..." wire:model="form.priority">
                            <flux:select.option>Low</flux:select.option>
                        </flux:select>
                    </div>
                </div>

                <div class="flex justify-between items-center w-full gap-4 my-6">
                    <div class="w-1/2">
                        <flux:input label="Due date" type="date" wire:model="form.dueDate"/>
                    </div>

                    <div class="w-1/2">
                        <flux:select variant="listbox" multiple label="Assigned Users" placeholder="Choose users..." wire:model="form.assignedUserIds">
                            @foreach($users as $user)
                                <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                <div class="flex">
                    <flux:spacer />

                    <flux:button type="submit" variant="primary">Save changes</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    @persist('toast')
    <flux:toast />
    @endpersist
</div>
