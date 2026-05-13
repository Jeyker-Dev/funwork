<?php

use Livewire\Component;
use App\Models\Task;
use Livewire\Attributes\On;

new class extends Component
{
    public ?int $projectId = null;
    public ?string $status = null;

    public function mount(int $projectId, ?string $status = null): void
    {
        $this->projectId = $projectId;
        $this->status = $status;
    }

    public function with(): array
    {
        return [
            'count' => Task::where('project_id', $this->projectId)
                ->where('status', $this->status)
                ->count(),
        ];
    }

    #[On('task-created')]
    #[On('tasks-reordered')]
    public function refresh(): void
    {
        // Empty handler — Livewire re-renders this component,
        // with() queries DB during render (AFTER all handlers complete).
    }
}; ?>

@php
    $countClasses = match ($status) {
        'Todo' => 'bg-orange-500',
        'Doing' => 'bg-yellow-500',
        'Done' => 'bg-green-500',
        default => 'bg-gray-500',
    };
@endphp

<div class="{{ $countClasses }} rounded-md w-6 h-6 p-2 flex items-center justify-center">
    <p class="text-sm font-bold text-white">
        {{ $count }}
    </p>
</div>