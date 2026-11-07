<?php

namespace App\Livewire\Forms;

use App\Models\InboxItem;
use App\Models\Notebook;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

    public ?int $areaId = null;

    protected function rules(): array
    {
        return [
            'areaId' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('project_id', $this->projectId)],
        ];
    }

    public function setItem(InboxItem $item): void
    {
        $this->item = $item;
        $this->name = 'Note from Inbox';
        $this->description = $item->content;
        $this->projectId = null;
        $this->areaId = null;
    }

    public function store(): Notebook
    {
        $this->validate();

        Gate::authorize('update', $this->item);

        $project = Project::findOrFail($this->projectId);
        Gate::authorize('create', [Notebook::class, $project]);

        $notebook = Notebook::create([
            'project_id' => $this->projectId,
            'name' => $this->name,
            'description' => $this->description,
            'created_by' => auth()->id(),
            'is_archived' => false,
            'is_shared' => false,
            'area_id' => $this->areaId ?: null,
        ]);

        $this->item->delete();

        return $notebook;
    }
}
