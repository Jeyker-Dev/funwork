<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Task;
use App\Models\Project;

new class extends Component
{
    public Project $project;
    public ?int $projectId = null;
    public string $activeFilter = 'all';
    public ?string $initialFilter = null;

    public function mount(Project $project, ?string $initialFilter = null): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->projectId = $project->id;
        $this->initialFilter = $initialFilter;
        $this->activeFilter = $initialFilter ?: 'all';
    }

    public function setFilter(string $filter): void
    {
        $this->activeFilter = $filter;
        $this->dispatch('slider-filter-changed', filter: $filter);
    }

    #[On('task-created')]
    public function refreshCounts(): void
    {
        $this->dispatch('$refresh');
    }

    public function with(): array
    {
        $baseQuery = Task::query()->where('project_id', $this->projectId);

        return [
            'counts' => [
                'all' => (clone $baseQuery)->count(),
                'Todo' => (clone $baseQuery)->where('status', 'Todo')->count(),
                'Doing' => (clone $baseQuery)->where('status', 'Doing')->count(),
                'Done' => (clone $baseQuery)->where('status', 'Done')->count(),
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
                'text-orange-500' => $activeFilter === 'Todo',
                'text-gray-500' => $activeFilter !== 'Todo',
            ])
            wire:click="setFilter('Todo')"
        >
            <span class="inline-flex items-center gap-2">
                <span>Todo</span>
                <livewire:projects.tasks.filter-count :count="$counts['Todo']" :active="$activeFilter === 'Todo'" :key="'count-todo-'.$counts['Todo'].'-'.($activeFilter === 'Todo' ? '1' : '0')" />
            </span>
            <span @class([
                'absolute left-0 -bottom-1 w-full h-0.5 bg-orange-500 transition-transform duration-200 origin-left',
                'scale-x-100' => $activeFilter === 'Todo',
                'scale-x-0 group-hover:scale-x-100' => $activeFilter !== 'Todo',
            ])></span>
        </button>

        <button
            type="button"
            @class([
                'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-orange-500',
                'text-orange-500' => $activeFilter === 'Doing',
                'text-gray-500' => $activeFilter !== 'Doing',
            ])
            wire:click="setFilter('Doing')"
        >
            <span class="inline-flex items-center gap-2">
                <span>Doing</span>
                <livewire:projects.tasks.filter-count :count="$counts['Doing']" :active="$activeFilter === 'Doing'" :key="'count-doing-'.$counts['Doing'].'-'.($activeFilter === 'Doing' ? '1' : '0')" />
            </span>
            <span @class([
                'absolute left-0 -bottom-1 w-full h-0.5 bg-orange-500 transition-transform duration-200 origin-left',
                'scale-x-100' => $activeFilter === 'Doing',
                'scale-x-0 group-hover:scale-x-100' => $activeFilter !== 'Doing',
            ])></span>
        </button>

        <button
            type="button"
            @class([
                'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-orange-500',
                'text-orange-500' => $activeFilter === 'Done',
                'text-gray-500' => $activeFilter !== 'Done',
            ])
            wire:click="setFilter('Done')"
        >
            <span class="inline-flex items-center gap-2">
                <span>Done</span>
                <livewire:projects.tasks.filter-count :count="$counts['Done']" :active="$activeFilter === 'Done'" :key="'count-done-'.$counts['Done'].'-'.($activeFilter === 'Done' ? '1' : '0')" />
            </span>
            <span @class([
                'absolute left-0 -bottom-1 w-full h-0.5 bg-orange-500 transition-transform duration-200 origin-left',
                'scale-x-100' => $activeFilter === 'Done',
                'scale-x-0 group-hover:scale-x-100' => $activeFilter !== 'Done',
            ])></span>
        </button>
    </div>
</div>
