<?php

use Livewire\Component;
use App\Models\Project;

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
            'tasks' => $this->project->tasks()->latest()->get(),
        ];
    }
};
?>

<div class="w-full">
    @foreach($tasks as $task)
        <a href="{{ route('show.task', ['project' => $project, 'task' => $task->id]) }}">
            <flux:card class="space-y-6 w-full">
                <div class="flex items-center justify-between">
                    <flux:badge size="lg" class="uppercase" color="orange">
                        {{ $task->priority }}
                    </flux:badge>

                    <flux:icon.ellipsis-vertical/>
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
        </a>
    @endforeach
</div>
