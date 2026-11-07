<?php

namespace App\Livewire\Forms;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

    public string $status = TaskStatus::Todo->value;

    public string $priority = TaskPriority::Low->value;

    public ?InboxItem $item = null;

    public ?int $areaId = null;

    protected function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'areaId' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('project_id', $this->projectId)],
        ];
    }

    public function setItem(InboxItem $item): void
    {
        $this->item = $item;
        $this->title = $item->content;
        $this->description = '';
        $this->projectId = null;
        $this->areaId = null;
        $this->status = TaskStatus::Todo->value;
        $this->priority = TaskPriority::Low->value;
    }

    public function store(): Task
    {
        $this->validate();

        Gate::authorize('update', $this->item);

        $project = Project::findOrFail($this->projectId);
        Gate::authorize('create', [Task::class, $project]);

        $position = Task::where('project_id', $this->projectId)->max('position');
        $position = $position ? $position + 1 : 1;

        $task = Task::create([
            'project_id' => $this->projectId,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'position' => $position,
            'priority' => $this->priority,
            'area_id' => $this->areaId ?: null,
        ]);

        $this->item->delete();

        return $task;
    }
}
