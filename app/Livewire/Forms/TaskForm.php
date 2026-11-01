<?php

namespace App\Livewire\Forms;

use App\Models\Project;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class TaskForm extends Form
{
    #[Validate('required')]
    public string $title = '';

    #[Validate('required')]
    public string $description = '';

    #[Validate('required')]
    public string $status = '';

    #[Validate('required')]
    public string $priority = '';

    #[Validate('nullable|date_format:Y-m-d')]
    public ?string $dueDate = null;

    #[Validate('nullable|array')]
    public array $assignedUserIds = [];

    #[Validate('nullable|integer')]
    public ?int $projectId = null;

    #[Validate('nullable|integer')]
    public ?int $position = null;

    protected function rules(): array
    {
        return [
            'assignedUserIds.*' => ['integer', Rule::in([auth()->id()])],
        ];
    }

    public function store(): void
    {
        $this->validate();

        $project = Project::findOrFail($this->projectId);
        Gate::authorize('create', [Task::class, $project]);

        $task = Task::create([
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->dueDate ?: null,
            'project_id' => $this->projectId,
            'position' => $this->position,
        ]);

        if (! empty($this->assignedUserIds)) {
            $task->users()->sync($this->assignedUserIds);
        }

        $this->reset();

        Flux::toast(
            heading: 'Task Created',
            text: 'The task has been successfully created.',
            variant: 'success',
            duration: 3000,
        );

        Flux::modals()->close();
    }
}
