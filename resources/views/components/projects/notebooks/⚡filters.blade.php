<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Notebook;

new class extends Component
{
    public string $activeFilter = 'all';
    public string $sort = 'recent';

    public function with(): array
    {
        $base = Notebook::query();
        return [
            'counts' => [
                'all' => $base->count(),
                'shared' => $base->where('is_shared', true)->count(),
                'archived' => $base->where('is_archived', true)->count(),
            ],
        ];
    }

    public function setFilter(string $filter): void
    {
        $this->activeFilter = $filter;
        $this->dispatch('notebooks-filter-changed', filter: $filter);
    }

    public function setSort(string $sort): void
    {
        $this->sort = $sort;
        $this->dispatch('notebooks-sort-changed', sort: $sort);
    }

    #[On('created-notebook')]
    public function refreshCounts(): void
    {
        $this->dispatch('$refresh');
    }
};
?>

<div class="flex items-center gap-4">
    <button type="button"
        @class([
            'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-orange-500',
            'text-orange-500' => $activeFilter === 'all',
            'text-gray-500' => $activeFilter !== 'all',
        ])
        wire:click="setFilter('all')"
    >
        All (<span>{{ $counts['all'] }}</span>)
    </button>

    <button type="button"
        @class([
            'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-orange-500',
            'text-orange-500' => $activeFilter === 'shared',
            'text-gray-500' => $activeFilter !== 'shared',
        ])
        wire:click="setFilter('shared')"
    >
        Shared (<span>{{ $counts['shared'] }}</span>)
    </button>

    <button type="button"
        @class([
            'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-orange-500',
            'text-orange-500' => $activeFilter === 'archived',
            'text-gray-500' => $activeFilter !== 'archived',
        ])
        wire:click="setFilter('archived')"
    >
        Archived (<span>{{ $counts['archived'] }}</span>)
    </button>

    <div class="w-0.5 h-6 bg-gray-300"></div>

    <button type="button"
        @class([
            'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-orange-500',
            'text-orange-500' => $sort === 'recent',
            'text-gray-500' => $sort !== 'recent',
        ])
        wire:click="setSort('recent')"
    >
        Sort: Recent
    </button>
    <button type="button"
        @class([
            'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-orange-500',
            'text-orange-500' => $sort === 'oldest',
            'text-gray-500' => $sort !== 'oldest',
        ])
        wire:click="setSort('oldest')"
    >
        Sort: Oldest
    </button>
</div>
