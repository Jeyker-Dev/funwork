@props([
    'task',
    'localToday',
])

@php
    use App\Enums\AreaColor;
    use App\Enums\TaskPriority;
    use App\Enums\TaskStatus;
    use App\Support\TaskDuePresenter;

    $isDone = $task->status === TaskStatus::Done;
    $isHigh = $task->priority === TaskPriority::High;
    $due = TaskDuePresenter::for($task, $localToday);
    $hasMetadata = $task->area !== null || $isHigh || $due !== null;
@endphp

<div wire:key="task-{{ $task->id }}" wire:sort:item="{{ $task->id }}">
    <article
        @class([
            'bg-surface-card rounded-xl p-3.5 border border-ghost shadow-xs hover:shadow-md transition-shadow duration-150 cursor-grab active:cursor-grabbing',
            'opacity-85' => $isDone,
        ])
    >
        <button
            type="button"
            wire:click="openTask({{ $task->id }})"
            class="w-full text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded-lg cursor-pointer"
        >
            @if($isDone)
                <div class="flex items-start gap-2.5">
                    <span class="size-4 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 mt-0.5" aria-hidden="true">
                        <flux:icon name="check" class="size-2.5 stroke-[3]" />
                    </span>

                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-medium text-ink-subtle line-through leading-snug break-words">
                            {{ $task->title }}
                        </h3>

                        @if($hasMetadata)
                            <div class="mt-2 flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                                @if($task->area)
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ink-subtle">
                                        <span class="size-1.5 rounded-full shrink-0 opacity-75 {{ AreaColor::dotClassFor($task->area->color) }}" aria-hidden="true"></span>
                                        <span class="truncate">{{ $task->area->name }}</span>
                                    </span>
                                @endif

                                @if($isHigh)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-danger-tint text-danger dark:bg-red-950/50 dark:text-red-400">
                                        High
                                    </span>
                                @endif

                                @if($due)
                                    <span class="text-[11px] font-medium text-ink-subtle">
                                        {{ $due->compactLabel }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <h3 class="text-sm font-medium text-ink leading-snug break-words">
                    {{ $task->title }}
                </h3>

                @if($hasMetadata)
                    <div class="mt-2.5 flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                        @if($task->area)
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ink-muted">
                                <span class="size-1.5 rounded-full shrink-0 {{ AreaColor::dotClassFor($task->area->color) }}" aria-hidden="true"></span>
                                <span class="truncate">{{ $task->area->name }}</span>
                            </span>
                        @endif

                        @if($isHigh)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-danger-tint text-danger dark:bg-red-950/50 dark:text-red-400">
                                High
                            </span>
                        @endif

                        @if($due)
                            <span @class([
                                'text-[11px] font-medium',
                                'text-danger dark:text-red-400' => $due->isOverdue,
                                'text-ink-muted bg-surface-low px-1.5 py-0.5 rounded' => $due->isToday,
                                'text-ink-subtle' => ! $due->isOverdue && ! $due->isToday,
                            ])>
                                {{ $due->compactLabel }}
                            </span>
                        @endif
                    </div>
                @endif
            @endif
        </button>
    </article>
</div>
