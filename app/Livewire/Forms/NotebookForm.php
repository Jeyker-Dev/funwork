<?php

namespace App\Livewire\Forms;

use App\Actions\Notebooks\CreateNotebookAction;
use App\Data\Notebooks\NotebookData;
use App\Models\Notebook;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class NotebookForm extends Form
{
    #[Validate('required|integer')]
    public ?int $projectId = null;

    #[Validate('required')]
    public string $name = '';

    #[Validate('nullable|string')]
    public ?string $description = '';

    #[Validate('required|integer')]
    public ?int $createdBy = null;

    public ?int $areaId = null;

    protected function rules(): array
    {
        return [
            'areaId' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('project_id', $this->projectId)],
        ];
    }

    public function store(): void
    {
        $this->validate();

        $project = Project::findOrFail($this->projectId);
        Gate::authorize('create', [Notebook::class, $project]);
        abort_if($this->createdBy !== auth()->id(), 403);

        $area = $this->areaId !== null
            ? $project->areas()->find($this->areaId)
            : null;

        app(CreateNotebookAction::class)(
            $project,
            auth()->user(),
            new NotebookData(
                name: $this->name,
                description: $this->description,
            ),
            $area,
        );

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
