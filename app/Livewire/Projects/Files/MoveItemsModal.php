<?php

namespace App\Livewire\Projects\Files;

use App\Models\Folder;
use App\Models\Project;
use App\Actions\Files\MoveSelectionAction;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Flux\Flux;

class MoveItemsModal extends Component
{
    public Project $project;
    
    public array $selectedIds = [];
    public ?int $targetFolderId = null;
    
    public string $newFolderName = '';
    public bool $isCreatingFolder = false;

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    #[On('open-move-modal')]
    public function open(array $selectedIds)
    {
        $this->selectedIds = $selectedIds;
        $this->targetFolderId = null;
        $this->isCreatingFolder = false;
        $this->newFolderName = '';
        
        Flux::modal('move-items-modal')->show();
    }

    public function navigateTo(?int $folderId)
    {
        $this->targetFolderId = $folderId;
        $this->isCreatingFolder = false;
    }

    public function navigateUp()
    {
        if ($this->targetFolderId) {
            $folder = Folder::find($this->targetFolderId);
            $this->targetFolderId = $folder ? $folder->parent_id : null;
        }
        $this->isCreatingFolder = false;
    }
    
    public function toggleCreateFolder()
    {
        $this->isCreatingFolder = !$this->isCreatingFolder;
        $this->newFolderName = '';
    }

    public function createFolder()
    {
        $this->validate([
            'newFolderName' => 'required|string|max:255'
        ], [], ['newFolderName' => 'Folder name']);

        $folder = Folder::create([
            'project_id' => $this->project->id,
            'parent_id' => $this->targetFolderId,
            'name' => $this->newFolderName,
        ]);

        $this->targetFolderId = $folder->id;
        $this->isCreatingFolder = false;
        $this->newFolderName = '';
    }

    public function move(MoveSelectionAction $action)
    {
        try {
            $action->execute($this->project, $this->selectedIds, $this->targetFolderId);
            Flux::modal('move-items-modal')->close();
            $this->dispatch('items-moved');
            Flux::toast('Items moved successfully.');
        } catch (ValidationException $e) {
            $this->addError('targetFolderId', $e->getMessage());
        }
    }

    public function render()
    {
        // Get the current folder's children
        $children = Folder::where('project_id', $this->project->id)
            ->where('parent_id', $this->targetFolderId)
            ->orderBy('name')
            ->get();
            
        // Get breadcrumbs
        $breadcrumbs = [];
        if ($this->targetFolderId) {
            $current = Folder::find($this->targetFolderId);
            if ($current) {
                $breadcrumbs = $current->breadcrumbs; // Assuming this accessor exists
            }
        }

        return view('livewire.projects.files.move-items-modal', [
            'children' => $children,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
