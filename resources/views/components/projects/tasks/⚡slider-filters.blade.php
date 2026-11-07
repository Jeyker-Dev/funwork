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
    public string $activeFilter = 'all';
    public ?string $initialFilter = null;
    public ?string $area = 'all';

    public function mount(Project $project, ?string $initialFilter = null, ?string $area = 'all'): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->projectId = $project->id;
        $this->initialFilter = $initialFilter;
        $this->activeFilter = $initialFilter ?: 'all';
        $this->area = $area ?: 'all';
    }

    public function setFilter(string $filter): void
    {
        $this->activeFilter = $filter;
        $this->dispatch('slider-filter-changed', filter: $filter);
    }

    #[On('area-filter-changed')]
    public function setAreaFilter(string $area): void
    {
        $this->area = $area;
    }

    #[On('task-created')]
    public function refreshCounts(): void
    {
        $this->dispatch('$refresh');
    }

    public function with(): array
    {
        $baseQuery = Task::query()->where('project_id', $this->projectId);
        QueryHelper::applyAreaFilter($baseQuery, $this->area);

        return [
            'counts' => [
                'all' => (clone $baseQuery)->count(),
                TaskStatus::Todo->value => (clone $baseQuery)->where('status', TaskStatus::Todo->value)->count(),
                TaskStatus::Doing->value => (clone $baseQuery)->where('status', TaskStatus::Doing->value)->count(),
                TaskStatus::Done->value => (clone $baseQuery)->where('status', TaskStatus::Done->value)->count(),
            ],
        ];
    }
};
?>

<div>
    <div class="flex justify-between items-center w-9/12 gap-4 my-6">
        <button
            type="button"
            @class([
                'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-orange-500',
                'text-orange-500' => $activeFilter === 'all',
                'text-gray-500' => $activeFilter !== 'all',
            ])
            wire:click="setFilter('all')"
        >
            <span class="inline-flex items-center gap-2">
                <span>All</span>
                <livewire:projects.tasks.filter-count :count="$counts['all']" :active="$activeFilter === 'all'" :key="'count-all-'.$counts['all'].'-'.($activeFilter === 'all' ? '1' : '0')" />
            </span>
            <span @class([
                'absolute left-0 -bottom-1 w-full h-0.5 bg-orange-500 transition-transform duration-200 origin-left',
                'scale-x-100' => $activeFilter === 'all',
                'scale-x-0 group-hover:scale-x-100' => $activeFilter !== 'all',
            ])></span>
        </button>

        <button
            type="button"
            @class([
                'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-orange-500',
                'text-orange-500' => $activeFilter === TaskStatus::Todo->value,
                'text-gray-500' => $activeFilter !== TaskStatus::Todo->value,
            ])
            wire:click="setFilter('{{ TaskStatus::Todo->value }}')"
        >
            <span class="inline-flex items-center gap-2">
                <span>{{ TaskStatus::Todo->value }}</span>
                <livewire:projects.tasks.filter-count :count="$counts[TaskStatus::Todo->value]" :active="$activeFilter === TaskStatus::Todo->value" :key="'count-todo-'.$counts[TaskStatus::Todo->value].'-'.($activeFilter === TaskStatus::Todo->value ? '1' : '0')" />
            </span>
            <span @class([
                'absolute left-0 -bottom-1 w-full h-0.5 bg-orange-500 transition-transform duration-200 origin-left',
                'scale-x-100' => $activeFilter === TaskStatus::Todo->value,
                'scale-x-0 group-hover:scale-x-100' => $activeFilter !== TaskStatus::Todo->value,
            ])></span>
        </button>

        <button
            type="button"
            @class([
                'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-orange-500',
                'text-orange-500' => $activeFilter === TaskStatus::Doing->value,
                'text-gray-500' => $activeFilter !== TaskStatus::Doing->value,
            ])
            wire:click="setFilter('{{ TaskStatus::Doing->value }}')"
        >
            <span class="inline-flex items-center gap-2">
                <span>{{ TaskStatus::Doing->value }}</span>
                <livewire:projects.tasks.filter-count :count="$counts[TaskStatus::Doing->value]" :active="$activeFilter === TaskStatus::Doing->value" :key="'count-doing-'.$counts[TaskStatus::Doing->value].'-'.($activeFilter === TaskStatus::Doing->value ? '1' : '0')" />
            </span>
            <span @class([
                'absolute left-0 -bottom-1 w-full h-0.5 bg-orange-500 transition-transform duration-200 origin-left',
                'scale-x-100' => $activeFilter === TaskStatus::Doing->value,
                'scale-x-0 group-hover:scale-x-100' => $activeFilter !== TaskStatus::Doing->value,
            ])></span>
        </button>

        <button
            type="button"
            @class([
                'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-orange-500',
                'text-orange-500' => $activeFilter === TaskStatus::Done->value,
                'text-gray-500' => $activeFilter !== TaskStatus::Done->value,
            ])
            wire:click="setFilter('{{ TaskStatus::Done->value }}')"
        >
            <span class="inline-flex items-center gap-2">
                <span>{{ TaskStatus::Done->value }}</span>
                <livewire:projects.tasks.filter-count :count="$counts[TaskStatus::Done->value]" :active="$activeFilter === TaskStatus::Done->value" :key="'count-done-'.$counts[TaskStatus::Done->value].'-'.($activeFilter === TaskStatus::Done->value ? '1' : '0')" />
            </span>
            <span @class([
                'absolute left-0 -bottom-1 w-full h-0.5 bg-orange-500 transition-transform duration-200 origin-left',
                'scale-x-100' => $activeFilter === TaskStatus::Done->value,
                'scale-x-0 group-hover:scale-x-100' => $activeFilter !== TaskStatus::Done->value,
            ])></span>
        </button>
    </div>
</div>
