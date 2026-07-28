<?php

namespace App\Livewire\Forms;

use App\Models\InboxItem;
use App\Models\Project;
use App\Models\Notebook;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ConvertInboxToNotebookForm extends Form
{
    #[Validate('required|exists:projects,id')]
    public ?int $projectId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string')]
    public string $description = '';

    public ?InboxItem $item = null;

    public function setItem(InboxItem $item): void
    {
        $this->item = $item;
        $this->name = 'Note from Inbox';
        $this->description = $item->content;
        $this->projectId = null;
    }

    public function store(): Notebook
    {
        $this->validate();

        $project = Project::findOrFail($this->projectId);
        if ($project->owner_id !== auth()->id()) {
            abort(403);
        }

        $notebook = Notebook::create([
            'project_id' => $this->projectId,
            'name' => $this->name,
            'description' => $this->description,
            'created_by' => auth()->id(),
            'is_archived' => false,
            'is_shared' => false,
        ]);

        $this->item->delete();

        return $notebook;
    }
}
