<?php

namespace App\Livewire\Forms;

use App\Models\Notebook;
use Flux\Flux;
use Livewire\Attributes\Validate;
use Livewire\Form;

class NotebookForm extends Form
{
    #[Validate('required|integer')]
    public ?int $projectId = null;

    #[Validate('required')]
    public string $name = '';

    #[Validate('required')]
    public string $description = '';

    #[Validate('required|integer')]
    public ?int $createdBy = null;

    public function store(): void
    {
        $this->validate();

        Notebook::create([
            'project_id' => $this->projectId,
            'created_by' => $this->createdBy,
            'name' => $this->name,
            'description' => $this->description,
        ]);

        $this->reset();

        Flux::toast(
            heading: 'Notebook Created',
            text: 'The notebook has been successfully created.',
            variant: 'success',
            duration: 3000,
        );

        Flux::modals()->close();
    }
}
