@props([
    'tasks',
    'localToday',
])

@php
    use App\Enums\AreaColor;
    use App\Enums\TaskPriority;
    use App\Enums\TaskStatus;
    use App\Support\TaskDuePresenter;
@endphp

<section aria-label="Tasks list" class="bg-surface-card rounded-2xl border border-ghost shadow-ambient overflow-hidden">
    @if($tasks->isEmpty())
        <div class="px-6 py-12 text-center space-y-3">
            <div class="inline-flex items-center justify-center size-10 rounded-full bg-surface-low text-ink-subtle mx-auto">
                <flux:icon name="rectangle-stack" class="size-5" aria-hidden="true" />
            </div>

            <div class="space-y-1">
                <p class="font-headline font-semibold text-base text-ink">No tasks found</p>
                <p class="text-sm text-ink-muted">Create a task or switch area filters to see more work.</p>
            </div>

            <flux:button variant="primary" size="sm" icon="plus" wire:click="openCreateModal('Todo')" class="max-md:min-h-11">
                New task
            </flux:button>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-low/60 text-[11px] font-semibold uppercase tracking-wider text-ink-muted">
                        <th scope="col" class="py-3 px-4">Title</th>
                        <th scope="col" class="hidden sm:table-cell py-3 px-4">Area</th>
                        <th scope="col" class="hidden sm:table-cell py-3 px-4">Priority</th>
                        <th scope="col" class="py-3 px-4">Status</th>
                        <th scope="col" class="py-3 px-4">Due date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tasks as $task)
                        @php
                            $isDone = $task->status === TaskStatus::Done;
                            $isHigh = $task->priority === TaskPriority::High;
                            $due = TaskDuePresenter::for($task, $localToday);
                        @endphp

                        <tr
                            wire:key="list-task-{{ $task->id }}"
                            wire:click="openTask({{ $task->id }})"
                            class="border-t border-ghost hover:bg-surface-low/80 transition-colors cursor-pointer"
                        >
                            <td class="py-3 px-4 sm:max-w-md">
                                <button
                                    type="button"
                                    wire:click.stop="openTask({{ $task->id }})"
                                    @class([
                                        'text-left text-sm font-medium leading-snug hover:text-accent-content focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded cursor-pointer',
                                        'line-through text-ink-subtle' => $isDone,
                                        'text-ink' => ! $isDone,
                                    ])
                                >
                                    {{ $task->title }}
                                </button>

                                @if($task->area || $isHigh)
                                    <div class="mt-1 flex flex-wrap items-center gap-2 sm:hidden text-[11px]">
                                        @if($task->area)
                                            <span class="inline-flex items-center gap-1 font-medium text-ink-muted">
                                                <span class="size-1.5 rounded-full shrink-0 {{ AreaColor::dotClassFor($task->area->color) }}" aria-hidden="true"></span>
                                                <span>{{ $task->area->name }}</span>
                                            </span>
                                        @endif

                                        @if($isHigh)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded font-medium bg-danger-tint text-danger dark:bg-red-950/50 dark:text-red-400">
                                                High
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <td class="hidden sm:table-cell py-3 px-4 whitespace-nowrap text-xs">
                                @if($task->area)
                                    <span class="inline-flex items-center gap-1.5 font-medium text-ink-muted">
                                        <span class="size-1.5 rounded-full shrink-0 {{ AreaColor::dotClassFor($task->area->color) }}" aria-hidden="true"></span>
                                        <span>{{ $task->area->name }}</span>
                                    </span>
                                @else
                                    <span class="text-ink-subtle">—</span>
                                @endif
                            </td>

                            <td class="hidden sm:table-cell py-3 px-4 whitespace-nowrap text-xs">
                                @if($isHigh)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded font-medium bg-danger-tint text-danger dark:bg-red-950/50 dark:text-red-400">
                                        High
                                    </span>
                                @else
                                    <span class="text-ink-muted">{{ $task->priority->value }}</span>
                                @endif
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap text-xs">
                                <span class="inline-flex items-center gap-1.5 font-medium text-ink">
                                    <span class="size-2 rounded-full shrink-0 {{ $task->status->dot() }}" aria-hidden="true"></span>
                                    <span>{{ $task->status->value }}</span>
                                </span>
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap text-xs">
                                @if($due)
                                    <span @class([
                                        'font-medium',
                                        'text-danger dark:text-red-400' => $due->isOverdue,
                                        'text-ink-muted' => ! $due->isOverdue,
                                    ])>
                                        {{ $due->compactLabel }}
                                    </span>
                                @else
                                    <span class="text-ink-subtle">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
