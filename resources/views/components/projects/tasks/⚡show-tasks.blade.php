<?php

use Livewire\Component;
use App\Models\Project;
use App\Models\Task;
use Livewire\Attributes\On;

new class extends Component {
    public ?int $projectId = null;
    public ?Project $project = null;

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
        $this->project = Project::findOrFail($projectId);
    }

    public function with(): array
    {
        return [
            'tasks' => $this->project->tasks()
                ->orderBy('position')
                ->orderByDesc('created_at')
                ->get(),
        ];
    }

    #[On('task-created')]
    public function refreshTasks(): void
    {
        $this->project->refresh();
    }

    public function updateTaskOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            Task::where('project_id', $this->projectId)
                ->where('id', $id)
                ->update(['position' => $index + 1]);
        }

        $this->project->refresh();
    }
};
?>

<div
    class="w-full flex flex-col gap-4"
    x-data="{
        updateOrder() {
            const ids = Array.from(this.$el.querySelectorAll('[x-sort\\:item]'))
                .map(el => el.getAttribute('x-sort:item'))
                .filter(Boolean);

            $wire.call('updateTaskOrder', ids);
        }
    }"
    x-sort="updateOrder"
>
    @foreach($tasks as $task)
        <flux:card
            class="space-y-6 w-full hover:cursor-pointer hover:shadow-md transition-shadow duration-200"
            x-sort:item="{{ $task->id }}"
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
    @endforeach
</div>
