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

<div class="flex flex-wrap items-center gap-3 sm:gap-4">
    <button type="button"
        @class([
            'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-accent-content max-md:min-h-11 inline-flex items-center',
            'text-accent-content' => $activeFilter === 'all',
            'text-ink-muted' => $activeFilter !== 'all',
        ])
        wire:click="setFilter('all')"
    >
        All (<span>{{ $counts['all'] }}</span>)
    </button>

    <button type="button"
        @class([
            'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-accent-content max-md:min-h-11 inline-flex items-center',
            'text-accent-content' => $activeFilter === 'shared',
            'text-ink-muted' => $activeFilter !== 'shared',
        ])
        wire:click="setFilter('shared')"
    >
        Shared (<span>{{ $counts['shared'] }}</span>)
    </button>

    <button type="button"
        @class([
            'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-accent-content max-md:min-h-11 inline-flex items-center',
            'text-accent-content' => $activeFilter === 'archived',
            'text-ink-muted' => $activeFilter !== 'archived',
        ])
        wire:click="setFilter('archived')"
    >
        Archived (<span>{{ $counts['archived'] }}</span>)
    </button>

    <div class="hidden sm:block w-px h-6 bg-ghost"></div>

    <button type="button"
        @class([
            'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-accent-content max-md:min-h-11 inline-flex items-center',
            'text-accent-content' => $sort === 'recent',
            'text-ink-muted' => $sort !== 'recent',
        ])
        wire:click="setSort('recent')"
    >
        Sort: Recent
    </button>
    <button type="button"
        @class([
            'text-sm font-bold uppercase transition-colors duration-200 cursor-pointer group relative hover:text-accent-content max-md:min-h-11 inline-flex items-center',
            'text-accent-content' => $sort === 'oldest',
            'text-ink-muted' => $sort !== 'oldest',
        ])
        wire:click="setSort('oldest')"
    >
        Sort: Oldest
    </button>
</div>
