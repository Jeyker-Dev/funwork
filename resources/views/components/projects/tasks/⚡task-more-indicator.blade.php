<?php

use Livewire\Component;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Project;
use Livewire\Attributes\On;

new class extends Component
{
    public Project $project;
    public ?int $projectId = null;
    public ?string $status = null;

    public function mount(Project $project, ?string $status = null): void
    {
        $this->project = $project;
        $this->projectId = $project->id;
        $this->status = $status;
    }

    public function with(): array
    {
        $count = Task::where('project_id', $this->projectId)
            ->where('status', $this->status)
            ->count();

        return [
            'hiddenTaskCount' => max(0, $count - 3),
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
    $indicatorClasses = match ($status) {
        TaskStatus::Todo->value => 'border-orange-200 bg-orange-50 hover:bg-orange-200 text-orange-500',
        TaskStatus::Doing->value => 'border-yellow-200 bg-yellow-50 hover:bg-yellow-200 text-yellow-600',
        TaskStatus::Done->value => 'border-green-200 bg-green-50 hover:bg-green-200 text-green-600',
        default => 'border-gray-200 bg-gray-50 hover:bg-gray-200 text-gray-500',
    };
@endphp

@php
    $modalName = 'show-more-tasks-'.$projectId.'-'.($status ?? 'all');
@endphp

<div class="w-full">
    <flux:modal.trigger name="{{ $modalName }}">
        <div class="w-full h-12 p-8 rounded-lg border flex items-center justify-between cursor-pointer transition-colors duration-200 group {{ $indicatorClasses }}">
            <p class="text-xs uppercase font-bold">+ {{ $hiddenTaskCount }} more tasks ...</p>

            <flux:icon.arrow-right variant="solid" class="w-5 h-5 font-bold! transform transition-transform duration-200 group-hover:translate-x-2"/>
        </div>
    </flux:modal.trigger>

    <flux:modal name="{{ $modalName }}" flyout>
        <x-projects.tasks.slider :project="$project" :status="$status" />
    </flux:modal>
</div>
