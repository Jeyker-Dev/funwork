<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\On;

new class extends Component
{
    public ?int $projectId = null;
    public Project $project;
    public ?string $status = null;

    public function mount(int $projectId, ?string $status = null): void
    {
        $this->projectId = $projectId;
        $this->status = $status;
        $this->project = Project::findOrFail($projectId);
    }

    public function with(): array
    {
        $query = $this->project->tasks();

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return [
            'count' => $query->count(),
        ];
    }

    #[On('task-created')]
    public function refreshCount(): void
    {
        $this->project = $this->project->fresh();
    }

    #[On('tasks-reordered')]
    public function refreshCountOnReorder(): void
    {
        $this->project = $this->project->fresh();
    }
};
?>

@php
    $countClasses = match ($status) {
        'Todo' => 'bg-orange-500',
        'Doing' => 'bg-yellow-500',
        'Done' => 'bg-green-500',
        default => 'bg-gray-500',
    };
@endphp

<div class="{{ $countClasses }} rounded-md w-6 h-6 p-2 flex items-center justify-center">
    <p class="text-sm font-bold text-white">
        {{ $count }}
    </p>
</div>
