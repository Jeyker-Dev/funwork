<?php

use App\Enums\TaskStatus;
use App\Livewire\Actions\ReorderTasks;
use App\Models\Project;
use App\Models\Task;
use App\Support\QueryHelper;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    public Project $project;
    public int $projectId;
    public array $columns = [];

    #[Url]
    public string $area = 'all';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->projectId = $project->id;

        $this->columns = array_map(fn (TaskStatus $status) => [
            'key' => $status->value,
            'title' => $status->value,
            'dot' => $status->dot(),
            'border' => $status->columnBorder(),
            'bg' => $status->columnBg(),
        ], TaskStatus::cases());
    }

    public function setArea(string $area): void
    {
        $this->area = $area;
        $this->dispatch('area-filter-changed', area: $area);
    }

    public function with(): array
    {
        $tasksByStatus = [];
        $countsByStatus = [];

        foreach ($this->columns as $column) {
            $status = $column['key'];

            $tasksQuery = Task::with('area')
                ->where('project_id', $this->projectId)
                ->where('status', $status)
                ->orderBy('position')
                ->orderByDesc('created_at');

            QueryHelper::applyAreaFilter($tasksQuery, $this->area);

            $tasksByStatus[$status] = $tasksQuery->limit(3)->get();

            $countQuery = Task::where('project_id', $this->projectId)
                ->where('status', $status);

            QueryHelper::applyAreaFilter($countQuery, $this->area);

            $countsByStatus[$status] = $countQuery->count();
        }

        return [
            'project' => $this->project,
            'tasksByStatus' => $tasksByStatus,
            'countsByStatus' => $countsByStatus,
        ];
    }

    #[On('task-created')]
    public function refreshBoard(): void
    {
        $this->with();
    }

    #[On('areas-updated')]
    public function onAreasUpdated(): void
    {
        $this->project->refresh();
    }

    public function reorderTasks(array $payload): void
    {
        app(ReorderTasks::class)($payload, $this->projectId);
    }
}; ?>

<div>
    {{-- Area filter tabs --}}
    <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-1">
        <flux:button
            size="sm"
            :variant="$area === 'all' || empty($area) ? 'filled' : 'ghost'"
            wire:click="setArea('all')"
        >
            All
        </flux:button>

        @foreach($project->areas as $projectArea)
            <flux:button
                size="sm"
                :variant="$area === $projectArea->slug ? 'filled' : 'ghost'"
                wire:click="setArea('{{ $projectArea->slug }}')"
            >
                <flux:badge size="sm" :color="$projectArea->color" class="mr-1.5 p-1!"/>
                {{ $projectArea->name }}
            </flux:button>
        @endforeach

        <flux:button
            size="sm"
            :variant="in_array($area, ['no-area', 'none', 'No area']) ? 'filled' : 'ghost'"
            wire:click="setArea('no-area')"
        >
            No area
        </flux:button>

        <flux:spacer />

        <livewire:projects.tasks.import-tasks :project="$project" />
    </div>

    <div class="grid grid-cols-3 gap-6">
        @foreach($this->columns as $column)

            @php
                $status = $column['key'];
                $tasks = $tasksByStatus[$status];
                $count = $countsByStatus[$status];
                $hiddenCount = max(0, $count - 3);

                $taskStatus = TaskStatus::tryFrom($status);
                $countClass = $taskStatus?->dot() ?? 'bg-gray-500';
                $indicatorClass = $taskStatus?->badge() ?? 'border-gray-200 bg-gray-50 hover:bg-gray-200 text-gray-500';

                $modalName = 'show-more-tasks-'.$projectId.'-'.$status;
            @endphp

            <x-projects.tasks.card class="{{ $column['border'] }} {{ $column['bg'] }}">
                {{-- Header: status + count --}}
                <div class="flex items-center gap-2 mb-4">
                    <div class="{{ $column['dot'] }} w-2 h-2 rounded-full"></div>
                    <p class="text-sm font-bold uppercase">{{ $column['title'] }}</p>
                    <div class="{{ $countClass }} rounded-md w-6 h-6 p-2 flex items-center justify-center">
                        <p class="text-sm font-bold text-white">{{ $count }}</p>
                    </div>
                </div>

                {{-- Create task button --}}
                <div class="flex flex-col items-center gap-4 w-full">
                    <livewire:projects.tasks.create-task :project="$project" :status="$status" :key="'create-task-'.$status" />

                    {{-- Task list with drag-and-drop --}}
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

                                $wire.reorderTasks(payload);
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

                    {{-- More tasks indicator --}}
                    @if($hiddenCount > 0)
                        <div class="w-full">
                            <flux:modal.trigger name="{{ $modalName }}">
                                <div class="w-full h-12 p-8 rounded-lg border flex items-center justify-between cursor-pointer transition-colors duration-200 group {{ $indicatorClass }}">
                                    <p class="text-xs uppercase font-bold">+ {{ $hiddenCount }} more tasks ...</p>
                                    <flux:icon.arrow-right variant="solid" class="w-5 h-5 font-bold! transform transition-transform duration-200 group-hover:translate-x-2"/>
                                </div>
                            </flux:modal.trigger>

                            <flux:modal name="{{ $modalName }}" flyout>
                                <x-projects.tasks.slider :project="$project" :status="$status" :area="$area" />
                            </flux:modal>
                        </div>
                    @endif
                </div>
            </x-projects.tasks.card>
        @endforeach
    </div>
</div>
