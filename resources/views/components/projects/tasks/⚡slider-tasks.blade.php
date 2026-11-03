<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Project;

new class extends Component {
    public Project $project;
    public ?int $projectId = null;
    public string $filter = 'all';
    public ?string $initialFilter = null;
    public int $page = 1;
    public int $perPage = 6;

    public function mount(Project $project, ?string $initialFilter = null): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->projectId = $project->id;
        $this->initialFilter = $initialFilter;
        $this->filter = $initialFilter ?: 'all';
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
            $statusEnum = $task->status instanceof TaskStatus ? $task->status : TaskStatus::tryFrom((string) $task->status);
            $accentBar = $statusEnum?->accent() ?? 'bg-gray-300';
            $badgeColor = $statusEnum?->fluxColor() ?? 'gray';

            $hasDueDate = !empty($task->due_date);
        @endphp

        <div
            class="relative bg-white rounded-2xl p-7 border shadow-sm transition-shadow duration-200 hover:shadow-md cursor-pointer"
            x-on:click="window.location='{{ route('show.task', ['project' => $project, 'task' => $task->id]) }}'">
            <span class="absolute left-0 top-0 h-full w-1.5 rounded-l-2xl {{ $accentBar }}"></span>

            <div class="pl-3">
                <div class="flex items-center justify-between gap-4">

                    <flux:badge size="xs" class="uppercase" color="{{ $badgeColor }}">
                        {{ $task->priority->value }}
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
