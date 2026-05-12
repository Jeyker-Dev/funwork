@props(['projectId', 'status' => null])

<div class="space-y-6">
    <div>
        <flux:heading size="lg">Tasks Slider</flux:heading>
        <flux:text class="mt-2">Make changes to your personal details.</flux:text>
    </div>

    <livewire:projects.tasks.slider-filters :projectId="$projectId" :initialFilter="$status" />

    <livewire:projects.tasks.slider-tasks :projectId="$projectId" :initialFilter="$status" />

    <livewire:projects.tasks.slider-pagination :projectId="$projectId" :initialFilter="$status" />
</div>
