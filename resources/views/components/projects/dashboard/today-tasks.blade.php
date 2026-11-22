@props([
    'tasks',
    'upcomingTasks' => collect(),
    'project',
    'home',
    'localToday',
])

@php
    use App\Enums\TaskStatus;
    use Illuminate\Support\Str;
@endphp

<section aria-labelledby="today-heading" class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-6 flex flex-col gap-5 flex-1">
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
        <div @class([
            'rounded-xl bg-surface-low p-6 text-center space-y-2',
            'flex-1 flex flex-col justify-center' => $upcomingTasks->isEmpty(),
        ])>
            <div class="inline-flex items-center justify-center size-10 rounded-full bg-surface-card text-accent-content mx-auto">
                <flux:icon name="check-circle" class="size-5" />
            </div>

            <p class="font-headline font-semibold text-base text-ink">Nothing due today</p>
            <p class="text-sm text-ink-muted">
                {{ $upcomingTasks->isNotEmpty() ? 'No overdue or due-today tasks left on your plate.' : 'You have no pending tasks in this project yet.' }}
            </p>
        </div>
    @else
        <div class="flex flex-col gap-2.5">
            @foreach($tasks as $task)
                @php
                    $isDone = $task->status === TaskStatus::Done;
                    $isOverdue = ! $isDone && $task->due_date !== null && $task->due_date->toDateString() < $localToday;
                    $badgeLabel = $isDone ? 'Done' : ($isOverdue ? 'Overdue' : 'Today');
                    $badgeTone = $isDone ? 'done' : ($isOverdue ? 'overdue' : 'muted');
                @endphp

                <x-projects.dashboard.today-task-row
                    :task="$task"
                    :project="$project"
                    :badge-label="$badgeLabel"
                    :badge-tone="$badgeTone"
                    wire-key-prefix="today-task"
                />
            @endforeach
        </div>
    @endif

    @if($upcomingTasks->isNotEmpty())
        <div class="flex flex-col gap-2.5 pt-1">
            <h3 class="text-sm font-semibold text-ink-muted">Upcoming</h3>

            @foreach($upcomingTasks as $task)
                @php
                    $dueLabel = $home?->formatUpcomingDueLabel($task);
                @endphp

                <x-projects.dashboard.today-task-row
                    :task="$task"
                    :project="$project"
                    :badge-label="$dueLabel ? 'Due '.$dueLabel : null"
                    badge-tone="muted"
                    wire-key-prefix="upcoming-task"
                />
            @endforeach
        </div>
    @endif
</section>
