<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\On;

new class extends Component
{
    public ?int $projectId = null;
    public Project $project;

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
        $this->project = Project::findOrFail($projectId);
    }

    public function with(): array
    {
        return [
            'count' => $this->project->tasks()->count(),
        ];
    }

    #[On('task-created')]
    public function refreshCount(): void
    {
        $this->project = $this->project->fresh();
    }
};
?>

<div class="bg-orange-500 rounded-md w-6 h-6 p-2 flex items-center justify-center">
    <p class="text-sm font-bold text-white">
        {{ $count }}
    </p>
</div>
