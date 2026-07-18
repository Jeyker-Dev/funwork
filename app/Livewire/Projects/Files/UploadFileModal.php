<?php

namespace App\Livewire\Projects\Files;

use App\Livewire\Forms\UploadFileForm;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithFileUploads;

class UploadFileModal extends Component
{
    use WithFileUploads;

    public Project $project;

    public ?int $currentFolderId = null;

    public UploadFileForm $form;

    public function mount(Project $project, ?int $currentFolderId = null)
    {
        $this->project = $project;
        $this->currentFolderId = $currentFolderId;
    }

    public function save()
    {
        $this->form->setContext($this->project, $this->currentFolderId);
        $this->form->store();
        $this->dispatch('file-uploaded');
    }

    public function render()
    {
        return view('livewire.projects.files.upload-file-modal');
    }
}
