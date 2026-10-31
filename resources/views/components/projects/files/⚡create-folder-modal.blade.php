<?php

use App\Livewire\Forms\FolderForm;
use App\Models\Project;
use Livewire\Component;

new class extends Component {
    public Project $project;

    public ?int $currentFolderId = null;

    public FolderForm $form;

    public function mount(Project $project, ?int $currentFolderId = null)
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->currentFolderId = $currentFolderId;
    }

    public function save()
    {
        $this->authorize('create', [\App\Models\Folder::class, $this->project]);

        $this->form->setContext($this->project, $this->currentFolderId);
        $this->form->store();
        $this->dispatch('folder-created');
    }
}; ?>

<flux:modal name="create-folder" class="min-w-[22rem]">
    <form wire:submit="save">
        <flux:heading size="lg" class="mb-4">Create New Folder</flux:heading>

        <flux:input wire:model="form.name" label="Folder Name" placeholder="e.g. Assets" class="mb-2" />
        <flux:error name="form.name" class="mb-4" />

        <div class="flex gap-2 justify-end mt-4">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">Create Folder</flux:button>
        </div>
    </form>
</flux:modal>
