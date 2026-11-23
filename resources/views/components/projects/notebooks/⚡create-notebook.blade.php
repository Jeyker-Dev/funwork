<?php

use App\Livewire\Forms\NotebookForm;
use App\Models\Notebook;
use App\Models\Project;
use Livewire\Component;

new class extends Component
{
    public NotebookForm $form;

    public ?Project $project = null;

    public ?int $createdBy = null;

    public string $buttonLabel = 'New notebook';

    public function mount(Project $project, string $buttonLabel = 'New notebook'): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->buttonLabel = $buttonLabel;
        $this->form->projectId = $project->id;

        if (auth()->check()) {
            $this->createdBy = auth()->id();
            $this->form->createdBy = $this->createdBy;
        }
    }

    public function save(): void
    {
        $this->authorize('create', [Notebook::class, $this->project]);

        $this->form->store();

        $this->form->projectId = $this->project->id;
        $this->form->createdBy = $this->createdBy;

        $this->dispatch('created-notebook');
    }
};
?>

<div>
    <flux:modal.trigger name="create-notebook">
        <flux:button icon="plus" variant="primary" class="whitespace-nowrap shrink-0 max-md:min-h-11">
            {{ $buttonLabel }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="create-notebook" class="w-[calc(100vw-2rem)] max-w-xl">
        <form wire:submit.prevent="save" class="space-y-6">
            <div>
                <flux:heading size="lg">Create Notebook</flux:heading>
                <flux:text class="mt-1">
                    Fill out the details below to create a new notebook for this project.
                </flux:text>
            </div>

            <flux:input label="Name" placeholder="Architecture" wire:model="form.name"/>

            <flux:textarea label="Description (optional)" placeholder="Optional details..." wire:model="form.description"/>

            <flux:select variant="listbox" label="Area" placeholder="No area" wire:model="form.areaId">
                <flux:select.option :value="null">No area</flux:select.option>
                @foreach($project->areas as $area)
                    <flux:select.option :value="$area->id">{{ $area->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-3 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Create notebook</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
