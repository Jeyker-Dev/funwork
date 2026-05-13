<?php

use Livewire\Component;
use App\Models\Project;
use App\Models\Task;
use App\Livewire\Actions\ReorderTasks;
use Livewire\Attributes\On;

new class extends Component {
    public int $projectId;
    public array $columns = [];

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;

        $this->columns = [
            ['key' => 'Todo', 'title' => 'Todo', 'dot' => 'bg-orange-500', 'border' => 'border-[rgba(255,128,64,0.48)]', 'bg' => 'bg-[rgba(255,128,64,0.16)]'],
            ['key' => 'Doing', 'title' => 'Doing', 'dot' => 'bg-yellow-500', 'border' => 'border-[rgba(255,220,70,0.38)]', 'bg' => 'bg-[rgba(255,220,70,0.17)]'],
            ['key' => 'Done', 'title' => 'Done', 'dot' => 'bg-green-500', 'border' => 'border-[rgba(38,186,112,0.33)]', 'bg' => 'bg-[rgba(38,186,112,0.13)]'],
        ];
    }

    public function with(): array
    {
        $project = Project::findOrFail($this->projectId);

        $tasksByStatus = [];
        $countsByStatus = [];

        foreach ($this->columns as $column) {
            $status = $column['key'];

            $tasksByStatus[$status] = Task::where('project_id', $this->projectId)
                ->where('status', $status)
                ->orderBy('position')
                ->orderByDesc('created_at')
                ->limit(3)
                ->get();

            $countsByStatus[$status] = Task::where('project_id', $this->projectId)
                ->where('status', $status)
                ->count();
        }

        return [
            'project' => $project,
            'tasksByStatus' => $tasksByStatus,
            'countsByStatus' => $countsByStatus,
        ];
    }

    #[On('task-created')]
    public function refreshBoard(): void
    {
        $this->with();
    }

    public function reorderTasks(array $payload): void
    {
        app(ReorderTasks::class)($payload, $this->projectId);
    }
}; ?>

<div class="grid grid-cols-3 gap-6">
    @foreach($this->columns as $column)

        @php
            $status = $column['key'];
            $tasks = $tasksByStatus[$status];
            $count = $countsByStatus[$status];
            $hiddenCount = max(0, $count - 3);

            $countClass = match ($status) {
                'Todo' => 'bg-orange-500',
                'Doing' => 'bg-yellow-500',
                'Done' => 'bg-green-500',
                default => 'bg-gray-500',
            };

            $indicatorClass = match ($status) {
                'Todo' => 'border-orange-200 bg-orange-50 hover:bg-orange-200 text-orange-500',
                'Doing' => 'border-yellow-200 bg-yellow-50 hover:bg-yellow-200 text-yellow-600',
                'Done' => 'border-green-200 bg-green-50 hover:bg-green-200 text-green-600',
                default => 'border-gray-200 bg-gray-50 hover:bg-gray-200 text-gray-500',
            };

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
                <livewire:projects.tasks.create-task :projectId="$projectId" :status="$status" :key="'create-task-'.$status" />

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
                                x-on:click="window.location='{{ route('show.task', ['project' => $projectId, 'task' => $task->id]) }}'"
                            >
                                <div class="flex items-center justify-between">
                                    <flux:badge size="lg" class="uppercase" color="orange">
                                        {{ $task->priority }}
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
                            <x-projects.tasks.slider :projectId="$projectId" :status="$status" />
                        </flux:modal>
                    </div>
                @endif
            </div>
        </x-projects.tasks.card>
    @endforeach
</div>
