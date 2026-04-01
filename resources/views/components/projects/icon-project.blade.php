@props([
    'project',
])

<div class="flex items-center justify-center bg-orange-100 rounded-md w-10 h-10">
    <flux:icon name="{{ $project->icon }}" variant="solid" class="text-amber-500 dark:text-amber-300" />
</div>
