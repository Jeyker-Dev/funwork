<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\On;

new class extends Component
{
    public ?int $projectId = null;
    public ?Project $project = null;
    public ?int $hiddenTaskCount = null;

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
        $this->project = Project::findOrFail($projectId);
    }

    public function with(): array
    {
        return [
            'hiddenTaskCount' => max(0, $this->project->tasks()->count() - 3),
        ];
    }

    #[On('task-created')]
    public function refreshHiddenCount(): void
    {
        $this->project->refresh();
        $this->hiddenTaskCount = max(0, $this->project->tasks()->count() - 3);
    }
};
?>

<div class="w-full">
    <flux:modal.trigger name="show-more-tasks">
        <div class="w-full h-12 p-8 rounded-lg border border-orange-200 bg-orange-50 flex items-center justify-between hover:bg-orange-200 cursor-pointer transition-colors duration-200 group">
            <p class="text-orange-500 text-xs uppercase font-bold">+ {{ $hiddenTaskCount }} more tasks ...</p>

            <flux:icon.arrow-right variant="solid" class="text-orange-500 w-5 h-5 font-bold! transform transition-transform duration-200 group-hover:translate-x-2"/>
        </div>
    </flux:modal.trigger>

    <flux:modal name="show-more-tasks" flyout>
        <x-projects.tasks.slider :projectId="$projectId" />
    </flux:modal>
</div>
