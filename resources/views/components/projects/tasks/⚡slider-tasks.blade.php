<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Task;

new class extends Component
{
    public ?int $projectId = null;
    public string $filter = 'all';
    public int $page = 1;
    public int $perPage = 6;

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
    }

    #[On('slider-filter-changed')]
    public function applyFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->page = 1;
    }

    #[On('slider-page-changed')]
    public function setPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    #[On('task-created')]
    public function refreshTasks(): void
    {
        $this->dispatch('$refresh');
    }

    public function with(): array
    {
        $query = Task::query()
            ->where('project_id', $this->projectId)
            ->orderBy('position')
            ->orderByDesc('created_at');

        if ($this->filter !== 'all') {
            $query->where('status', $this->filter);
        }

        $tasks = $query
            ->skip(($this->page - 1) * $this->perPage)
            ->take($this->perPage)
            ->get();

        return [
            'tasks' => $tasks,
        ];
    }
};
?>

<div class="grid grid-cols-2 gap-4 max-w-lg">
    @forelse($tasks as $task)
        <div class="bg-white rounded-lg p-6 border">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <flux:badge size="lg" class="uppercase" color="orange">
                        {{ $task->priority }}
                    </flux:badge>

                    <flux:avatar tooltip="Assigned user" src="https://unavatar.io/x/calebporzio"/>
                </div>

                <div>
                    <flux:heading size="lg" class="font-bold!">
                        {{ $task->title }}
                    </flux:heading>

                    <flux:text class="text-gray-500 mt-2">
                        {{ $task->description }}
                    </flux:text>
                </div>
            </div>
        </div>
    @empty
        <div class="text-sm text-gray-500">No tasks found.</div>
    @endforelse
</div>
