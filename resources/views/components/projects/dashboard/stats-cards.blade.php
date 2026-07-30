@props(['stats'])

<div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm p-6 flex flex-col gap-4">
    <flux:heading size="lg">Overview</flux:heading>
    
    <div class="grid grid-cols-3 gap-4 mt-2">
        <div class="flex flex-col">
            <flux:text class="text-sm">Pending Tasks</flux:text>
            <flux:heading size="xl">{{ $stats['tasks_count'] }}</flux:heading>
        </div>
        <div class="flex flex-col">
            <flux:text class="text-sm">Notebooks</flux:text>
            <flux:heading size="xl">{{ $stats['notebooks_count'] }}</flux:heading>
        </div>
        <div class="flex flex-col">
            <flux:text class="text-sm">Files</flux:text>
            <flux:heading size="xl">{{ $stats['files_count'] }}</flux:heading>
        </div>
    </div>
</div>
