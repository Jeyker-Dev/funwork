@props(['tasks', 'project'])

@php
    $localToday = \App\Support\DateHelper::userToday();
@endphp

<div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 flex flex-col gap-4">
    <div class="flex justify-between items-center">
        <flux:heading size="lg">Urgent Tasks</flux:heading>
        <flux:button size="sm" variant="ghost" :href="route('project.tasks', $project)" class="max-md:min-h-11">View All</flux:button>
    </div>

    <div class="flex flex-col gap-2 mt-2 overflow-y-auto">
        @forelse($tasks as $task)
            <div class="flex items-center justify-between p-3 rounded-xl border border-ghost bg-surface-low">
                <div class="flex flex-col min-w-0">
                    <span class="text-sm font-medium truncate text-ink">{{ $task->title }}</span>
                    @if($task->due_date && $task->due_date->toDateString() < $localToday)
                        <span class="text-xs text-red-600 dark:text-red-400 font-medium">Overdue</span>
                    @elseif($task->due_date && $task->due_date->toDateString() === $localToday)
                        <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">Due today</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center p-4 text-center">
                <flux:text class="text-sm text-ink-muted">No urgent tasks!</flux:text>
            </div>
        @endforelse
    </div>
</div>
