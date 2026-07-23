<?php

namespace App\Livewire\Projects\Files;

use App\Livewire\Forms\RenameItemForm;
use App\Models\Folder;
use App\Models\ProjectFile;
use Livewire\Component;
use Flux\Flux;

use Livewire\Attributes\On;

class RenameItemModal extends Component
{
    public RenameItemForm $form;
    
    public string $type = '';

    #[On('open-rename-file')]
    public function mountFile(int $fileId)
    {
        $file = ProjectFile::findOrFail($fileId);
        // Add auth check
        if ($file->project->owner_id !== auth()->id()) {
            abort(403);
        }
        $this->type = 'file';
        $this->form->setContextForFile($file);
        Flux::modal('rename-item-modal')->show();
    }

    #[On('open-rename-folder')]
    public function mountFolder(int $folderId)
    {
        $folder = Folder::findOrFail($folderId);
        // Add auth check
        if ($folder->project->owner_id !== auth()->id()) {
            abort(403);
        }
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

    public function render()
    {
        return view('livewire.projects.files.rename-item-modal');
    }
}
