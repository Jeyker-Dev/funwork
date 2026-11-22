@props([
    'tasks',
    'project',
    'localToday',
    'upNextText' => null,
])

@php
    use App\Enums\TaskStatus;
    use Illuminate\Support\Str;
@endphp

<section aria-labelledby="today-heading" class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-6 flex flex-col gap-5">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 min-w-0">
            <h2 id="today-heading" class="font-headline font-bold text-xl text-ink tracking-tight">Today</h2>

            @if($tasks->isNotEmpty())
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-surface-low text-ink-muted">
                    {{ $tasks->count() }} {{ Str::plural('task', $tasks->count()) }}
                </span>
            @endif
        </div>

        <a
            href="{{ route('project.tasks', $project) }}"
            wire:navigate
            class="inline-flex items-center max-md:min-h-11 text-sm font-semibold text-accent-content hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded-md px-1 shrink-0"
        >
            View all tasks
        </a>
    </div>

    @if($tasks->isEmpty())
        <div class="space-y-4">
            <div class="rounded-xl bg-surface-low p-6 text-center space-y-2">
                <div class="inline-flex items-center justify-center size-10 rounded-full bg-surface-card text-accent-content mx-auto">
                    <flux:icon name="check-circle" class="size-5" />
                </div>

                <p class="font-headline font-semibold text-base text-ink">Nothing due today</p>
                <p class="text-sm text-ink-muted">
                    {{ $upNextText ? 'No overdue or due-today tasks left on your plate.' : 'You have no pending tasks in this project yet.' }}
                </p>
            </div>

            @if($upNextText)
                <div class="p-4 rounded-xl bg-surface-low flex items-center gap-3 min-w-0">
                    <flux:icon name="clock" class="size-5 text-accent-content shrink-0" />
                    <p class="text-sm font-medium text-ink-muted truncate">{{ $upNextText }}</p>
                </div>
            @endif
        </div>
    @else
        <div class="flex flex-col gap-2.5">
            @foreach($tasks as $task)
                @php
                    $isDone = $task->status === TaskStatus::Done;
                    $isOverdue = ! $isDone && $task->due_date !== null && $task->due_date->toDateString() < $localToday;
                @endphp

                <div
                    wire:key="today-task-{{ $task->id }}"
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

                    <div class="shrink-0">
                        @if($isDone)
                            <span class="text-xs font-medium text-ink-subtle">Done</span>
                        @elseif($isOverdue)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-danger-tint text-danger dark:bg-red-950/50 dark:text-red-400">
                                Overdue
                            </span>
                        @else
                            <span class="text-xs font-medium text-ink-muted">Today</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
