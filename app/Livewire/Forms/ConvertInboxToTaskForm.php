<?php

namespace App\Livewire\Forms;

use App\Models\InboxItem;
use App\Models\Project;
use App\Models\Task;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ConvertInboxToTaskForm extends Form
{
    #[Validate('required|exists:projects,id')]
    public ?int $projectId = null;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('nullable|string')]
    public string $description = '';

    public ?InboxItem $item = null;

    public function setItem(InboxItem $item): void
    {
        $this->item = $item;
        $this->title = $item->content;
        $this->description = '';
        $this->projectId = null;
    }

    public function store(): Task
    {
        $this->validate();

        $project = Project::findOrFail($this->projectId);
        if ($project->owner_id !== auth()->id()) {
            abort(403);
        }

        $position = Task::where('project_id', $this->projectId)->max('position');
        $position = $position ? $position + 1 : 1;

        $task = Task::create([
            'project_id' => $this->projectId,
            'title' => $this->title,
            'description' => $this->description,
            'status' => 'Todo',
            'position' => $position,
            'priority' => 'Low',
        ]);

        $this->item->delete();

        return $task;
    }
}
