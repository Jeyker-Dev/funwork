<?php

namespace App\Livewire\Forms;

use App\Actions\Projects\CreateDefaultAreasAction;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ProjectForm extends Form
{
    #[Validate('required')]
    public string $name = '';

    #[Validate('required')]
    public string $description = '';

    #[Validate('required')]
    public string $icon = '';

    #[Validate('required')]
    public string $type = '';

    #[Validate('nullable|date_format:Y-m-d')]
    public ?string $archiveDate = null;

    #[Validate('required')]
    public string $status = '';

    #[Validate('nullable|date_format:Y-m-d')]
    public ?string $dueDate = null;

    #[Validate('required|integer')]
    public ?int $ownerId = null;

    public function setProject(Project $project): void
    {
        $this->name = $project->name;
        $this->description = (string) ($project->description ?? '');
        $this->icon = $project->icon ?: 'folder';
        $this->type = (string) $project->type;
        $this->status = (string) $project->status;
        $this->dueDate = $project->due_at ? substr((string) $project->due_at, 0, 10) : null;
        $this->archiveDate = $project->archived_at ? substr((string) $project->archived_at, 0, 10) : null;
        $this->ownerId = $project->owner_id;
    }

    public function update(Project $project): void
    {
        Gate::authorize('update', $project);

        $this->ownerId = $project->owner_id;
        $this->validate();

        $project->update([
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'type' => $this->type,
            'status' => $this->status,
            'due_at' => $this->dueDate ?: null,
        ]);
    }

    public function store(): void
    {
        $this->validate();

        Gate::authorize('create', Project::class);
        abort_if($this->ownerId !== auth()->id(), 403);

        $project = Project::create([
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'type' => $this->type,
            'archived_at' => $this->archiveDate ?: null,
            'status' => $this->status,
            'due_at' => $this->dueDate ?: null,
            'owner_id' => $this->ownerId,
        ]);

        app(CreateDefaultAreasAction::class)($project);

        $this->reset();

        Flux::toast(
            heading: 'Project Created',
            text: 'The project has been successfully created.',
            variant: 'success',
            duration: 3000,
        );

        Flux::modals()->close();
    }
}
