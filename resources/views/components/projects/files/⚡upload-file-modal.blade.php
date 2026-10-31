<?php

use App\Livewire\Forms\UploadFileForm;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Project $project;

    public ?int $currentFolderId = null;

    public UploadFileForm $form;

    public function mount(Project $project, ?int $currentFolderId = null)
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->currentFolderId = $currentFolderId;
    }

    public function save()
    {
        $this->authorize('create', [\App\Models\ProjectFile::class, $this->project]);

        $this->form->setContext($this->project, $this->currentFolderId);
        $this->form->store();
        $this->dispatch('file-uploaded');
    }
}; ?>

<flux:modal name="upload-file" class="min-w-[22rem]">
    <form wire:submit="save">
        <flux:heading size="lg" class="mb-4">Upload Files</flux:heading>

        <div class="mb-2">
            <flux:label>Select files</flux:label>
            <input type="file" multiple wire:model="form.files" class="block w-full text-sm text-gray-500 mt-2
                file:mr-4 file:py-2 file:px-4
                file:rounded-md file:border-0
                file:text-sm file:font-semibold
                file:bg-orange-50 file:text-orange-700
                hover:file:bg-orange-100" />
        </div>
        <flux:error name="form.files" />
        <flux:error name="form.files.*" />

        <div class="flex gap-2 justify-end mt-6">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <!-- Show loading state on upload button -->
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="save">Upload</span>
                <span wire:loading wire:target="save">Uploading...</span>
            </flux:button>
        </div>
    </form>
</flux:modal>
