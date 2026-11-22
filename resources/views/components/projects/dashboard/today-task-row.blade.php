@props([
    'task',
    'project',
    'badgeLabel' => null,
    'badgeTone' => 'muted',
    'wireKeyPrefix' => 'today-task',
])

@php
    use App\Enums\TaskStatus;

    $isDone = $task->status === TaskStatus::Done;
    $badgeClass = match ($badgeTone) {
        'overdue' => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-danger-tint text-danger dark:bg-red-950/50 dark:text-red-400',
        'done' => 'text-xs font-medium text-ink-subtle',
        default => 'text-xs font-medium text-ink-muted',
    };
@endphp

<div
    wire:key="{{ $wireKeyPrefix }}-{{ $task->id }}"
    class="flex items-center justify-between gap-3 p-3.5 rounded-xl transition-colors {{ $isDone ? 'bg-surface opacity-80' : 'bg-surface-low hover:bg-surface-high/60' }}"
>
    <div class="flex items-center gap-3 min-w-0 flex-1">
        <button
            type="button"
            role="checkbox"
            aria-checked="{{ $isDone ? 'true' : 'false' }}"
            aria-label="{{ $isDone ? 'Reopen task: '.$task->title : 'Mark task done: '.$task->title }}"
            wire:click="toggleTaskCompletion({{ $task->id }})"
            wire:loading.attr="disabled"
            wire:target="toggleTaskCompletion({{ $task->id }})"
            class="inline-flex items-center justify-center max-md:min-h-11 max-md:min-w-11 md:size-7 shrink-0 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
        >
            <span class="size-5 rounded-full flex items-center justify-center transition-colors duration-200 ease-out {{ $isDone ? 'bg-brand-strong text-white' : 'bg-surface-card border-2 border-ink-subtle/50 hover:border-brand-strong' }}">
                @if($isDone)
                    <flux:icon name="check" class="size-3.5 stroke-[2.5]" />
                @endif
            </span>
        </button>

        <div class="flex flex-col gap-1 min-w-0">
            <a
                href="{{ route('show.task', ['project' => $project, 'task' => $task]) }}"
                wire:navigate
                class="text-sm font-medium leading-snug break-words hover:text-accent-content focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded {{ $isDone ? 'line-through text-ink-subtle' : 'text-ink' }}"
            >
                {{ $task->title }}
            </a>

            @if($task->area)
                <div class="flex items-center">
                    <flux:badge size="sm" :color="$task->area->color">{{ $task->area->name }}</flux:badge>
                </div>
            @endif
        </div>
    </div>

    @if($badgeLabel !== null)
        <div class="shrink-0">
            <span class="{{ $badgeClass }}">{{ $badgeLabel }}</span>
        </div>
    @endif
</div>
