@props(['project', 'status' => null, 'area' => 'all'])

<div class="space-y-6">
    <div>
        <flux:heading size="lg">Tasks Slider</flux:heading>
        <flux:text class="mt-2">Make changes to your personal details.</flux:text>
    </div>

    <livewire:projects.tasks.slider-filters :project="$project" :initialFilter="$status" :area="$area" />

    <livewire:projects.tasks.slider-tasks :project="$project" :initialFilter="$status" :area="$area" />

    <livewire:projects.tasks.slider-pagination :project="$project" :initialFilter="$status" :area="$area" />
</div>
