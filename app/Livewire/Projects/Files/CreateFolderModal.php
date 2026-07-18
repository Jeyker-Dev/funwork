<?php

namespace App\Livewire\Projects\Files;

use App\Livewire\Forms\FolderForm;
use App\Models\Project;
use Livewire\Component;

class CreateFolderModal extends Component
{
    public Project $project;

    public ?int $currentFolderId = null;

    public FolderForm $form;

    public function mount(Project $project, ?int $currentFolderId = null)
    {
        $this->project = $project;
        $this->currentFolderId = $currentFolderId;
    }

    public function save()
    {
        $this->form->setContext($this->project, $this->currentFolderId);
        $this->form->store();
        $this->dispatch('folder-created');
    }

    public function render()
    {
        return view('livewire.projects.files.create-folder-modal');
    }
}
