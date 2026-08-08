<?php

namespace App\Livewire\Forms;

use App\Models\Project;
use Flux\Flux;
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

    public function store(): void
    {
        $this->validate();

        Project::create([
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'type' => $this->type,
            'archived_at' => $this->archiveDate ?: null,
            'status' => $this->status,
            'due_at' => $this->dueDate ?: null,
            'owner_id' => $this->ownerId,
        ]);

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
