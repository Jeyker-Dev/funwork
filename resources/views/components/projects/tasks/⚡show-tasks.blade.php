<?php

use App\Actions\Tasks\ReorderTasksAction;
use App\Models\Project;
use App\Models\Task;
use App\Support\QueryHelper;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Project $project;
    public ?int $projectId = null;
    public ?string $status = null;
    public ?string $area = null;

    public function mount(Project $project, ?string $status = null, ?string $area = null): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->projectId = $project->id;
        $this->status = $status;
        $this->area = $area;
    }

    public function with(): array
    {
        $query = Task::with('area')
            ->where('project_id', $this->projectId)
            ->orderBy('position')
            ->orderByDesc('created_at');

        if ($this->status) {
            $query->where('status', $this->status);
        }

        QueryHelper::applyAreaFilter($query, $this->area);

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
        app(ReorderTasksAction::class)($payload, $this->projectId);

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
                    <div class="flex items-center gap-2">
                        <flux:badge size="lg" class="uppercase" color="orange">
                            {{ $task->priority->value }}
                        </flux:badge>
                        @if($task->area)
                            <flux:badge size="sm" :color="$task->area->color">
                                {{ $task->area->name }}
                            </flux:badge>
                        @endif
                    </div>

                    <flux:icon.arrows-pointing-out x-sort:handle x-on:click.stop/>
                </div>

                <div>
                    <flux:heading size="lg" class="font-bold!">
                        {{ $task->title }}
                    </flux:heading>

                    @if(filled($task->description_excerpt))
                        <flux:text class="text-gray-500 mt-2 line-clamp-2 break-words">
                            {{ $task->description_excerpt }}
                        </flux:text>
                    @endif
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
