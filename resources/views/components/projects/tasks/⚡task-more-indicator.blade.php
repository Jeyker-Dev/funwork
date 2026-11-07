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

        $count = $query->count();

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
    $taskStatus = $status ? TaskStatus::tryFrom($status) : null;
    $indicatorClasses = $taskStatus?->badge() ?? 'border-gray-200 bg-gray-50 hover:bg-gray-200 text-gray-500';
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
        <x-projects.tasks.slider :project="$project" :status="$status" :area="$area" />
    </flux:modal>
</div>
