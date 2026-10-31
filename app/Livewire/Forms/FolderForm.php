<?php

namespace App\Livewire\Forms;

use App\Models\Folder;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Form;

class FolderForm extends Form
{
    public ?Project $project = null;

    public ?int $currentFolderId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    public function setContext(Project $project, ?int $currentFolderId = null): void
    {
        $this->project = $project;
        $this->currentFolderId = $currentFolderId;
    }

    public function store(): void
    {
        $this->validate();

        Gate::authorize('create', [Folder::class, $this->project]);

        if ($this->currentFolderId !== null) {
            $parentFolder = Folder::findOrFail($this->currentFolderId);
            Gate::authorize('view', $parentFolder);
            abort_if($parentFolder->project_id !== $this->project->id, 403);
        }

        $this->project->folders()->create([
            'name' => $this->name,
            'created_by' => Auth::id(),
            'parent_id' => $this->currentFolderId,
        ]);

        $this->reset('name');

        Flux::toast(
            heading: 'Folder Created',
            text: 'The folder has been successfully created.',
            variant: 'success',
            duration: 3000,
        );

        Flux::modals()->close();
    }
}
