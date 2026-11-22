@props([
    'continue',
])

@php
    use App\Enums\ProjectType;
    use Illuminate\Support\Carbon;

    $project = $continue->project;
@endphp

<section aria-labelledby="continue-heading" class="space-y-4">
    <h2 id="continue-heading" class="font-headline font-bold text-xl text-ink tracking-tight">
        Continue
    </h2>

    <div class="w-full bg-brand-tint rounded-2xl border border-ghost p-6 sm:p-7 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex-1 min-w-0 space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <x-projects.icon-project
                    :project="$project"
                    containerClass="bg-surface-card! border border-ghost rounded-xl shrink-0"
                />

                <div class="flex flex-wrap items-center gap-2.5 min-w-0">
                    <h3 class="font-headline font-bold text-xl text-ink tracking-tight truncate">
                        {{ $project->name }}
                    </h3>

                    <span
                        data-project-type-tag
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ ProjectType::badgeClassesFor($project->type) }}"
                    >
                        {{ $project->type }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium text-ink-muted md:ml-auto">
                    @if($project->due_at)
                        <span>Due {{ Carbon::parse($project->due_at)->format('M d, Y') }}</span>
                    @endif

                    <span>Edited {{ $continue->lastActivityAt->diffForHumans() }}</span>
                </div>
            </div>

            <div class="space-y-2 w-full">
                <div class="flex justify-between items-center text-xs font-medium text-ink-muted">
                    <span>{{ $continue->doneTasksCount }} of {{ $continue->totalTasksCount }} tasks done</span>
                    <span class="font-semibold text-brand-deep dark:text-orange-400">{{ $continue->progressPercent }}%</span>
                </div>

                <div
                    class="w-full bg-surface-card/80 dark:bg-zinc-800 rounded-full h-2 overflow-hidden"
                    role="progressbar"
                    aria-valuenow="{{ $continue->progressPercent }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="{{ $project->name }} task completion"
                >
                    <div class="bg-brand h-2 rounded-full" style="width: {{ $continue->progressPercent }}%"></div>
                </div>

                <p class="text-xs font-medium text-ink-muted pt-0.5 truncate">
                    <span class="font-semibold text-ink">Next:</span>
                    {{ $continue->nextTask?->title ?? 'No pending tasks' }}
                </p>
            </div>
        </div>

        <div class="flex items-center md:self-center shrink-0">
            <flux:button
                :href="route('projects.show', ['project' => $project])"
                wire:navigate
                class="w-full md:w-auto min-h-11 bg-surface-card! hover:bg-surface-low! text-ink! border border-ghost! shadow-xs"
            >
                Open project
            </flux:button>
        </div>
    </div>
</section>
