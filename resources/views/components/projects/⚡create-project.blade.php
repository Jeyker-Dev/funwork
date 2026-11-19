<?php

use Livewire\Component;
use App\Livewire\Forms\ProjectForm;

new class extends Component {

    public ProjectForm $form;

    public string $elementName = '';

    public function mount($elementName): void
    {
        $this->elementName = $elementName;
    }

    public function with(): array
    {
        return [
            'user' => auth()->user(),
        ];
    }

    public function save(): void
    {
        $this->form->store();

        $this->dispatch('project-created');
    }
};
?>

<div>
    @if($this->elementName === 'header')
        <flux:modal.trigger name="create-project">
            <flux:button icon="plus" variant="primary" aria-label="New project" class="max-md:min-h-11 max-sm:min-w-11 max-sm:gap-0 max-sm:px-2.5" href="#">
                <span class="hidden sm:inline">New project</span>
            </flux:button>
        </flux:modal.trigger>
    @else
        <flux:modal.trigger name="create-project">
            <div
                class="flex flex-col items-center justify-center gap-4 border-2 border-dashed border-ghost bg-surface-card rounded-2xl p-6 mt-6 max-w-96 cursor-pointer">
                <div class="flex flex-col items-center justify-center bg-surface-high rounded-full w-10 h-10 md:w-12 md:h-12">
                    <flux:icon.plus variant="solid" class="text-ink size-6 md:size-7"/>
                </div>

                <p class="text-ink font-bold md:text-lg">
                    Create your first project
                </p>

                <p class="text-ink-muted font-normal text-center w-8/12 text-sm md:text-base">
                    Start your first workspace and organize your tasks effortlessly.
                </p>
            </div>
        </flux:modal.trigger>
    @endif

    <flux:modal name="create-project" class="w-[calc(100vw-2rem)] max-w-xl">
        <form wire:submit.prevent="save">
            <div class="space-y-6">
                <div>
                    <flux:heading class="text-lg md:text-xl">
                        Create Project
                    </flux:heading>

                    <flux:text class="text-xs md:text-sm mt-2">
                        Define the parameters of your new editorial or technical workspace.
                    </flux:text>
                </div>

                <div class="flex flex-col md:flex-row md:items-center justify-between w-full gap-4 md:gap-0">
                    <div class="md:w-6/12">
                        <flux:input label="Project Name" placeholder="Example: Q3 Brand Expansion" wire:model="form.name"/>
                    </div>

                    <div class="">
                        <flux:radio.group label="Icon" wire:model="form.icon" variant="segmented">
                            <flux:radio icon="wrench" value="wrench"/>
                            <flux:radio icon="pencil-square" value="pencil-square"/>
                            <flux:radio icon="eye" value="eye"/>
                        </flux:radio.group>
                    </div>
                </div>

                <div>
                    <flux:textarea label="Description"
                                   placeholder="Describe the objectives and key results for this project..."
                                    wire:model="form.description"/>
                </div>

                <div class="flex flex-col md:flex-row md:items-center justify-between w-full gap-4 md:gap-0">
                    <div class="md:w-5/12">
                        <flux:select variant="listbox" placeholder="Choose type..." label="Project type" wire:model="form.type">
                            <flux:select.option>Programming</flux:select.option>
                            <flux:select.option>Study</flux:select.option>
                            <flux:select.option>Music</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="md:w-5/12">
                        <flux:select variant="listbox" placeholder="Choose status..." label="Initial status" wire:model="form.status">
                            <flux:select.option value="planning">Planning</flux:select.option>
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="on-hold">On Hold</flux:select.option>
                        </flux:select>
                    </div>
                </div>


                <div class="flex flex-col md:flex-row md:items-center justify-between w-full gap-4 md:gap-0">
                    <div class="md:w-5/12">
                        <flux:input label="Due date" type="date" wire:model="form.dueDate"/>
                    </div>

                    <div class="md:w-5/12">
                        <flux:select variant="listbox" placeholder="Choose Owner..." label="Project Owner" wire:model="form.ownerId">
                            <flux:select.option value="{{ $user->id }}">{{ $user->display_name }}</flux:select.option>
                        </flux:select>
                    </div>
                </div>

                <div class="flex">
                    <flux:modal.close>
                        <flux:button>Cancel</flux:button>
                    </flux:modal.close>

                    <flux:spacer/>

                    <flux:button type="submit" variant="primary">Create</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    @persist('toast')
        <flux:toast />
    @endpersist
</div>
