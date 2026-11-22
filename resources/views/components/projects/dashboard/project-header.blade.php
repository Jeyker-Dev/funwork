@props(['project', 'stats'])

@php
    use App\Enums\ProjectType;
    use App\Enums\TaskStatus;
    use Illuminate\Support\Carbon;

    $statusCounts = $stats->statusCounts();
@endphp

<section aria-label="Project overview" class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-7 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start sm:items-center gap-4 min-w-0">
            <x-projects.icon-project
                :project="$project"
                containerClass="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl shrink-0"
                iconClass="size-6 sm:size-7"
            />

            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2.5 min-w-0">
                    <h1 class="font-headline font-bold text-2xl sm:text-3xl tracking-tight text-ink break-words">
                        {{ $project->name }}
                    </h1>

                    <span
                        data-project-type-tag
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border shrink-0 {{ ProjectType::badgeClassesFor($project->type) }}"
                    >
                        {{ $project->type }}
                    </span>
                </div>

                @if($project->due_at)
                    <div class="flex items-center gap-1.5 text-sm text-ink-muted">
                        <flux:icon name="calendar" class="size-4 text-ink-subtle shrink-0" />
                        <span>Due {{ Carbon::parse($project->due_at)->format('M d, Y') }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="text-sm font-medium text-ink-muted shrink-0">
            {{ $stats->doneTasksCount }} of {{ $stats->totalTasksCount }} tasks done
        </div>
    </div>

    <div class="space-y-3">
        <div
            class="w-full bg-surface-highest rounded-full h-2.5 overflow-hidden"
            role="progressbar"
            aria-valuenow="{{ $stats->progressPercent }}"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-label="{{ $project->name }} task completion"
        >
            <div
                class="bg-brand h-2.5 rounded-full transition-all duration-200 ease-out"
                style="width: {{ $stats->progressPercent }}%"
            ></div>
        </div>

        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
            @foreach(TaskStatus::cases() as $status)
                <div class="inline-flex items-center gap-2 text-xs font-medium text-ink-muted">
                    <span class="size-2 rounded-full {{ $status->dot() }}" aria-hidden="true"></span>
                    <span>{{ $status->value }} {{ $statusCounts[$status->value] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</section>
