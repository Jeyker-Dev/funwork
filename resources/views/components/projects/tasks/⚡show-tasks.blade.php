<?php

use Livewire\Component;
use App\Models\Task;
use App\Models\Project;
use App\Livewire\Actions\ReorderTasks;
use Livewire\Attributes\On;

new class extends Component {
    public Project $project;
    public ?int $projectId = null;
    public ?string $status = null;

    public function mount(Project $project, ?string $status = null): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->projectId = $project->id;
        $this->status = $status;
    }

    public function with(): array
    {
        $query = Task::where('project_id', $this->projectId)
            ->orderBy('position')
            ->orderByDesc('created_at');

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return [
            'tasks' => $query->limit(3)->get(),
        ];
    }

    #[On('task-created')]
    public function refreshTasks(): void
    {
        // Livewire auto-renders — with() queries fresh data
    }

    #[On('tasks-reordered')]
    public function syncOrder(array $payload = []): void
    {
        app(ReorderTasks::class)($payload, $this->projectId);

        // Livewire auto-renders — with() queries fresh data
    }
}; ?>

<div
    class="w-full flex flex-col gap-4"
    data-task-column
    data-status="{{ $status }}"
    x-data="{
        updateAllColumns() {
            const columns = Array.from(document.querySelectorAll('[data-task-column]'));
            const payload = {};

            columns.forEach((column) => {
                const status = column.getAttribute('data-status');
                const ids = Array.from(column.querySelectorAll('[x-sort\\:item]'))
                    .map(el => el.getAttribute('x-sort:item'))
                    .filter(Boolean);

                if (!status) {
                    return;
                }

                payload[status] = ids;
            });

            window.Livewire?.dispatch('tasks-reordered', { payload });
        }
    }"
    x-sort:group="tasks"
    x-sort="updateAllColumns"
>
    @foreach($tasks as $task)
        <div
            wire:key="task-{{ $task->id }}"
            x-sort:item="{{ $task->id }}"
        >
            <flux:card
                class="space-y-6 w-full hover:cursor-pointer hover:shadow-md transition-shadow duration-200"
                x-on:click="window.location='{{ route('show.task', ['project' => $project, 'task' => $task->id]) }}'"
            >
                <div class="flex items-center justify-between">
                    <flux:badge size="lg" class="uppercase" color="orange">
                        {{ $task->priority->value }}
                    </flux:badge>

                    <flux:icon.arrows-pointing-out x-sort:handle x-on:click.stop/>
                </div>

                <div>
                    <flux:heading size="lg" class="font-bold!">
                        {{ $task->title }}
                    </flux:heading>

                    <flux:text class="text-gray-500 mt-2">
                        {{ $task->description }}
                    </flux:text>
                </div>

                <div class="flex items-center justify-between">
                    <flux:avatar tooltip="Caleb Porzio" src="https://unavatar.io/x/calebporzio"/>

                    <div class="flex items-center gap-2">
                        <flux:icon.clock/>
                        <p class="text-sm text-gray-500">
                            Due: {{ $task->due_date }}
                        </p>
                    </div>
                </div>
            </flux:card>
        </div>
    @endforeach
</div>
