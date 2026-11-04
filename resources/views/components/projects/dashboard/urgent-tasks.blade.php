@props(['tasks', 'project'])

@php
    $localToday = \App\Support\DateHelper::userToday();
@endphp

<div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm p-6 flex flex-col gap-4">
    <div class="flex justify-between items-center">
        <flux:heading size="lg">Urgent Tasks</flux:heading>
        <flux:button size="sm" variant="ghost" :href="route('project.tasks', $project)">View All</flux:button>
    </div>

    <div class="flex flex-col gap-2 mt-2 overflow-y-auto">
        @forelse($tasks as $task)
            <div class="flex items-center justify-between p-3 rounded-xl border border-neutral-100 dark:border-zinc-800 bg-neutral-50 dark:bg-zinc-900/50">
                <div class="flex flex-col truncate">
                    <span class="text-sm font-medium truncate text-neutral-900 dark:text-white">{{ $task->title }}</span>
                    @if($task->due_date && $task->due_date->toDateString() < $localToday)
                        <span class="text-xs text-red-500 font-medium">Overdue</span>
                    @elseif($task->due_date && $task->due_date->toDateString() === $localToday)
                        <span class="text-xs text-amber-500 font-medium">Due today</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center p-4 text-center">
                <flux:text class="text-sm text-neutral-500">No urgent tasks!</flux:text>
            </div>
        @endforelse
    </div>
</div>
