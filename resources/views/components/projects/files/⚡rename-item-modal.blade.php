<?php

use App\Livewire\Forms\RenameItemForm;
use App\Models\Folder;
use App\Models\ProjectFile;
use Livewire\Component;
use Flux\Flux;
use Livewire\Attributes\On;

new class extends Component {
    public RenameItemForm $form;
    
    public string $type = '';

    #[On('open-rename-file')]
    public function mountFile(int $fileId)
    {
        $file = ProjectFile::findOrFail($fileId);
        $this->authorize('update', $file);

        $this->type = 'file';
        $this->form->setContextForFile($file);
        Flux::modal('rename-item-modal')->show();
    }

    #[On('open-rename-folder')]
    public function mountFolder(int $folderId)
    {
        $folder = Folder::findOrFail($folderId);
        $this->authorize('update', $folder);

        $this->type = 'folder';
        $this->form->setContextForFolder($folder);
        Flux::modal('rename-item-modal')->show();
    }

    public function save()
    {
        if ($this->form->updateItem()) {
            $this->dispatch('item-renamed');
            Flux::modal('rename-item-modal')->close();
        }
    }
}; ?>

<div>
    <flux:modal name="rename-item-modal" class="md:w-96">
        <form wire:submit="save">
            <flux:heading size="lg">Rename {{ $type === 'file' ? 'File' : 'Folder' }}</flux:heading>
            
            <div class="mt-4">
                <flux:input 
                    wire:model="form.name" 
                    label="Name" 
                    placeholder="Enter new name"
                >
                    @if($type === 'file' && $form->extension)
                        <x-slot name="suffix">
                            {{ $form->extension }}
                        </x-slot>
                    @endif
                </flux:input>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
