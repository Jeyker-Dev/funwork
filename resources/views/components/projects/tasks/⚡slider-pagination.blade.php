<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Task;

new class extends Component
{
    public ?int $projectId = null;
    public string $filter = 'all';
    public ?string $initialFilter = null;
    public int $page = 1;
    public int $perPage = 6;

    public function mount(int $projectId, ?string $initialFilter = null): void
    {
        $this->projectId = $projectId;
        $this->initialFilter = $initialFilter;
        $this->filter = $initialFilter ?: 'all';
    }

    #[On('slider-filter-changed')]
    public function resetPage(string $filter): void
    {
        $this->filter = $filter;
        $this->page = 1;
        $this->dispatch('slider-page-changed', page: 1);
    }

    #[On('slider-page-changed')]
    public function setPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    #[On('task-created')]
    public function refreshTotals(): void
    {
        $this->dispatch('$refresh');
    }

    public function with(): array
    {
        $query = Task::query()->where('project_id', $this->projectId);

        if ($this->filter !== 'all') {
            $query->where('status', $this->filter);
        }

        $total = $query->count();
        $totalPages = (int) max(1, ceil($total / $this->perPage));

        if ($this->page > $totalPages) {
            $this->page = $totalPages;
        }

        return [
            'total' => $total,
            'totalPages' => $totalPages,
        ];
    }

    public function previousPage(): void
    {
        $page = max(1, $this->page - 1);
        $this->dispatch('slider-page-changed', page: $page);
    }

    public function nextPage(): void
    {
        $page = $this->page + 1;
        $this->dispatch('slider-page-changed', page: $page);
    }
};
?>

<div>
    @if($totalPages > 1)
        <div class="flex items-center justify-between max-w-lg">
            <button
                type="button"
                class="text-sm font-semibold text-gray-500 hover:text-orange-500 transition-colors"
                wire:click="previousPage"
                @disabled($page <= 1)
            >
                Previous
            </button>

            <p class="text-sm text-gray-500">
                Page {{ $page }} of {{ $totalPages }}
            </p>

            <button
                type="button"
                class="text-sm font-semibold text-gray-500 hover:text-orange-500 transition-colors"
                wire:click="nextPage"
                @disabled($page >= $totalPages)
            >
                Next
            </button>
        </div>
    @endif
</div>
