<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Support\QueryHelper;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Project $project;
    public ?int $projectId = null;
    public ?string $status = null;
    public ?string $area = null;

    public function mount(Project $project, ?string $status = null, ?string $area = null): void
    {
        $this->project = $project;
        $this->projectId = $project->id;
        $this->status = $status;
        $this->area = $area;
    }

    #[On('area-filter-changed')]
    public function setAreaFilter(?string $area): void
    {
        $this->area = $area;
    }

    public function with(): array
    {
        $query = Task::where('project_id', $this->projectId)
            ->where('status', $this->status);

        QueryHelper::applyAreaFilter($query, $this->area);

        return [
            'count' => $query->count(),
        ];
    }

    #[On('task-created')]
    #[On('tasks-reordered')]
    public function refresh(): void
    {
        // Empty handler — Livewire re-renders this component,
        // with() queries DB during render (AFTER all handlers complete).
    }
}; ?>

@php
    $countClasses = match ($status) {
        TaskStatus::Todo->value => 'bg-orange-500',
        TaskStatus::Doing->value => 'bg-yellow-500',
        TaskStatus::Done->value => 'bg-green-500',
        default => 'bg-gray-500',
    };
@endphp

<div class="{{ $countClasses }} rounded-md w-6 h-6 p-2 flex items-center justify-center">
    <p class="text-sm font-bold text-white">
        {{ $count }}
    </p>
</div>
