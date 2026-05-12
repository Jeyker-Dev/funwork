<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Task;

new class extends Component {
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

<div class="grid grid-cols-2 gap-7 max-w-lg">
    @forelse($tasks as $task)
        @php
            $accentBar = match ($task->status) {
                'Todo' => 'bg-orange-400',
                'Doing' => 'bg-yellow-400',
                'Done' => 'bg-green-400',
                default => 'bg-gray-300',
            };

            $badgeColor = match ($task->status) {
                'Todo' => 'orange',
                'Doing' => 'yellow',
                'Done' => 'green',
                default => 'gray',
            };

            $hasDueDate = !empty($task->due_date);
        @endphp

        <div
            class="relative bg-white rounded-2xl p-7 border shadow-sm transition-shadow duration-200 hover:shadow-md cursor-pointer"
            x-on:click="window.location='{{ route('show.task', ['project' => $projectId, 'task' => $task->id]) }}'">
            <span class="absolute left-0 top-0 h-full w-1.5 rounded-l-2xl {{ $accentBar }}"></span>

            <div class="pl-3">
                <div class="flex items-center justify-between gap-4">

                    <flux:badge size="xs" class="uppercase" color="{{ $badgeColor }}">
                        {{ $task->priority }}
                    </flux:badge>

                    <flux:avatar tooltip="Assigned user" size="sm" class="shrink-0"
                                 src="https://unavatar.io/x/calebporzio"/>
                </div>

                <div class="pt-4">
                    <flux:heading size="lg" class="font-semibold leading-snug line-clamp-1 text-center w-full">
                        {{ $task->title }}
                    </flux:heading>
                </div>

                <div class="flex items-center gap-5 text-xs text-gray-500 mt-5">
                    <div class="flex items-center gap-2">
                        <flux:icon.calendar-days class="w-3.5 h-3.5 {{ $hasDueDate ? '' : 'text-gray-300' }}"/>
                        <span>{{ $hasDueDate ? $task->due_date : '—' }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.chat-bubble-left-right class="w-3.5 h-3.5 text-gray-300"/>
                        <span>—</span>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="text-sm text-gray-500">No tasks found.</div>
    @endforelse
</div>
