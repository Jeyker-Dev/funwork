<?php

namespace App\Livewire\Forms;

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
    public string $dueDate = '';

    #[Validate('required|integer')]
    public ?int $assignedUserId = null;

    #[Validate('nullable|integer')]
    public ?int $projectId = null;

    #[Validate('nullable|integer')]
    public ?int $position = null;

    public function store()
    {
        $this->validate();
    }
}
