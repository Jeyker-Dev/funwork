<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\On;

new class extends Component
{
    public ?int $projectId = null;
    public ?Project $project = null;
    public ?int $hiddenTaskCount = null;
    public ?string $status = null;

    public function mount(int $projectId, ?string $status = null): void
    {
        $this->projectId = $projectId;
        $this->status = $status;
        $this->project = Project::findOrFail($projectId);
    }

    public function with(): array
    {
        $query = $this->project->tasks();

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return [
            'hiddenTaskCount' => max(0, $query->count() - 3),
        ];
    }

    #[On('task-created')]
    public function refreshHiddenCount(): void
    {
        $this->project->refresh();

        $query = $this->project->tasks();

        if ($this->status) {
            $query->where('status', $this->status);
        }

        $this->hiddenTaskCount = max(0, $query->count() - 3);
    }

    #[On('tasks-reordered')]
    public function refreshHiddenCountOnReorder(): void
    {
        $this->refreshHiddenCount();
    }
};
?>

@php
    $indicatorClasses = match ($status) {
        'Todo' => 'border-orange-200 bg-orange-50 hover:bg-orange-200 text-orange-500',
        'Doing' => 'border-yellow-200 bg-yellow-50 hover:bg-yellow-200 text-yellow-600',
        'Done' => 'border-green-200 bg-green-50 hover:bg-green-200 text-green-600',
        default => 'border-gray-200 bg-gray-50 hover:bg-gray-200 text-gray-500',
    };
@endphp

@php
    $modalName = 'show-more-tasks-'.$projectId.'-'.($status ?? 'all');
@endphp

<div class="w-full">
    <flux:modal.trigger name="{{ $modalName }}">
        <div class="w-full h-12 p-8 rounded-lg border flex items-center justify-between cursor-pointer transition-colors duration-200 group {{ $indicatorClasses }}">
            <p class="text-xs uppercase font-bold">+ {{ $hiddenTaskCount }} more tasks ...</p>

            <flux:icon.arrow-right variant="solid" class="w-5 h-5 font-bold! transform transition-transform duration-200 group-hover:translate-x-2"/>
        </div>
    </flux:modal.trigger>

    <flux:modal name="{{ $modalName }}" flyout>
        <x-projects.tasks.slider :projectId="$projectId" :status="$status" />
    </flux:modal>
</div>
