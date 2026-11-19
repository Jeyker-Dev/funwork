@props([
    'project',
    'containerClass' => '',
    'iconClass' => '',
])

<div class="flex items-center justify-center bg-brand-tint rounded-md w-10 h-10 {{ $containerClass }}">
    <flux:icon name="{{ $project->icon }}" variant="solid" class="text-brand {{ $iconClass }}" />
</div>
