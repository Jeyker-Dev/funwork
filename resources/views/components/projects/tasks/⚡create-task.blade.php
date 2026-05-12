<?php

use Livewire\Component;
use App\Livewire\Forms\TaskForm;
use App\Models\User;

new class extends Component
{
    public TaskForm $form;
    public ?int $projectId = null;

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
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
        $this->form->position = 1; // Set the position for the new task (you may want to calculate this based on existing tasks)
        $this->form->store();
        $this->dispatch('task-created');
    }
};
?>

<div class="w-full">
    <flux:modal.trigger name="create-task">
        <div class="border-2 border-dashed border-[rgba(255,128,64,0.48)] bg-[rgba(255,128,64,0.23)] hover:bg-[rgba(255,128,64,0.33)] flex items-center justify-center p-10 cursor-pointer rounded-lg">
            <flux:icon.plus-circle class="text-orange-500"/>
        </div>
    </flux:modal.trigger>

    <flux:modal name="create-task" class="w-xl">
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
                        <flux:select variant="listbox" label="Assigned User" placeholder="Choose user..." wire:model="form.assignedUserId">
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
