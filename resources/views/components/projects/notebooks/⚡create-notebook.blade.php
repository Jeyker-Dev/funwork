<?php

use Livewire\Component;
use App\Livewire\Forms\NotebookForm;
use App\Models\Project;

new class extends Component {
    public NotebookForm $form;
    public ?Project $project = null;
    public ?int $createdBy = null;

    public function mount(int $projectId): void
    {
        $this->project = Project::findOrFail($projectId);

        $this->form->projectId = $this->project->id;

        if (auth()->check()) {
            $this->createdBy = auth()->id();
            $this->form->createdBy = $this->createdBy;
        }
    }

    public function save(): void
    {
        $this->form->store();
    }
};
?>

<div>
    <flux:modal.trigger name="create-notebook">
        <flux:button icon="plus" variant="primary">
            Create Notebook
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="create-notebook" class="w-xl">
        <form wire:submit.prevent="save">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Create Notebook</flux:heading>
                    <flux:text class="mt-2">
                        Fill out the details below to create a new notebook for this project.
                    </flux:text>

                </div>

                <flux:input label="Name" placeholder="Review Project Map" wire:model="form.name"/>

                <flux:textarea label="Description" placeholder="Add details..." wire:model="form.description"/>

                <div class="flex">
                    <flux:spacer/>

                    <flux:button type="submit" variant="primary">Save changes</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    @persist('toast')
    <flux:toast/>
    @endpersist
</div>
