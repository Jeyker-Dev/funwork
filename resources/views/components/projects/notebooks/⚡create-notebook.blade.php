<?php

use Livewire\Component;
use App\Livewire\Forms\NotebookForm;

new class extends Component
{
    public NotebookForm $form;

    public function save(): void
    {
        $this->form->store();
    }
};
?>

<div>
    <flux:modal.trigger name="create-notebook">
        <flux:button icon="plus" variant="primary" wire:click="save">
            Create Notebook
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="create-notebook" class="w-xl">
        <form wire:submit.prevent="save">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Create Task</flux:heading>
                    <flux:text class="mt-2">
                        Fill out the details below to create a new notebook for this project.
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
