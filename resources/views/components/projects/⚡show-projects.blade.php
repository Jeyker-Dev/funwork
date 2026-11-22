<?php

use App\Actions\Projects\ListUserProjectsAction;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public string $search = '';

    public function with(ListUserProjectsAction $listUserProjects): array
    {
        return [
            'projects' => $listUserProjects(auth()->user(), $this->search),
        ];
    }

    #[On('project-created')]
    public function refreshProjects(): void
    {
    }

    #[On('search-updated')]
    public function syncSearch(string $search): void
    {
        $this->search = $search;
    }
};
?>

<section x-data="{ view: 'grid' }" aria-labelledby="all-projects-heading" class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <h2 id="all-projects-heading" class="font-headline font-bold text-xl text-ink tracking-tight">
            All projects
        </h2>

        <div
            class="hidden sm:inline-flex items-center bg-surface-card border border-ghost rounded-xl p-1 gap-1 shadow-xs"
            role="group"
            aria-label="Project layout"
        >
            <button
                type="button"
                @click="view = 'grid'"
                :aria-pressed="(view === 'grid').toString()"
                aria-label="Grid view"
                title="Grid view"
                :class="view === 'grid' ? 'bg-brand-tint text-brand-deep dark:text-orange-400' : 'text-ink-muted hover:text-ink'"
                class="min-h-8 min-w-8 p-1.5 rounded-lg flex items-center justify-center transition-colors duration-150 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
                <flux:icon.squares-2x2 class="size-4" />
            </button>

            <button
                type="button"
                @click="view = 'list'"
                :aria-pressed="(view === 'list').toString()"
                aria-label="List view"
                title="List view"
                :class="view === 'list' ? 'bg-brand-tint text-brand-deep dark:text-orange-400' : 'text-ink-muted hover:text-ink'"
                class="min-h-8 min-w-8 p-1.5 rounded-lg flex items-center justify-center transition-colors duration-150 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
                <flux:icon.list-bullet class="size-4" />
            </button>
        </div>
    </div>

    @if($projects->isEmpty())
        <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient px-6 py-10 text-center">
            <p class="font-headline font-semibold text-base text-ink">
                No matching projects
            </p>
            <p class="text-sm text-ink-muted mt-1">
                Try a different search term to find your workspace.
            </p>
        </div>
    @else
        <div
            data-projects-grid
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
            :class="view === 'grid' ? 'grid' : 'grid sm:hidden'"
        >
            @foreach($projects as $project)
                @php
                    $totalTasks = $project->totalTasksCount();
                    $doneTasks = $project->doneTasksCount();
                    $progressPercent = $project->taskProgressPercent();
                @endphp

                <a
                    wire:key="project-card-{{ $project->id }}"
                    href="{{ route('projects.show', ['project' => $project]) }}"
                    wire:navigate
                    class="block group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded-2xl"
                >
                    <article class="h-full bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 flex flex-col justify-between gap-5">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <x-projects.icon-project :project="$project" containerClass="rounded-xl shrink-0" />

                                <span
                                    data-project-type-tag
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ ProjectType::badgeClassesFor($project->type) }}"
                                >
                                    {{ $project->type }}
                                </span>
                            </div>

                            <div>
                                <h3 class="font-headline font-bold text-lg text-ink group-hover:text-accent-content transition-colors duration-150 truncate">
                                    {{ $project->name }}
                                </h3>
                                <p class="mt-1.5 text-sm text-ink-muted leading-relaxed line-clamp-2">
                                    {{ $project->description }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3 pt-1">
                            <div>
                                <div class="flex justify-end text-xs font-medium text-ink-muted mb-1.5">
                                    <span>{{ $doneTasks }} of {{ $totalTasks }} tasks</span>
                                </div>

                                <div class="w-full bg-surface-highest rounded-full h-1.5 overflow-hidden">
                                    <div
                                        class="{{ ProjectStatus::progressBarClassFor($project->status) }} h-1.5 rounded-full"
                                        style="width: {{ $progressPercent }}%"
                                    ></div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 pt-1">
                                <span
                                    class="size-2 rounded-full {{ ProjectStatus::dotClassFor($project->status) }}"
                                    aria-hidden="true"
                                ></span>
                                <span class="text-xs font-medium text-ink-muted">
                                    {{ ProjectStatus::labelFor($project->status) }}
                                </span>
                            </div>
                        </div>
                    </article>
                </a>
            @endforeach
        </div>

        <div
            data-projects-list
            x-show="view === 'list'"
            x-cloak
            class="hidden sm:block"
        >
            <div
                tabindex="0"
                role="region"
                aria-labelledby="all-projects-heading"
                class="bg-surface-card rounded-2xl border border-ghost shadow-ambient overflow-x-auto"
            >
                <table class="w-full text-left border-collapse">
                    <caption class="sr-only">All projects</caption>
                    <thead>
                        <tr class="bg-surface-low/60 text-xs font-semibold text-ink-muted">
                            <th scope="col" class="py-3.5 px-5">Project</th>
                            <th scope="col" class="py-3.5 px-4">Type</th>
                            <th scope="col" class="py-3.5 px-4">Progress</th>
                            <th scope="col" class="py-3.5 px-4">Status</th>
                            <th scope="col" class="py-3.5 px-5">Due date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projects as $project)
                            @php
                                $totalTasks = $project->totalTasksCount();
                                $doneTasks = $project->doneTasksCount();
                                $progressPercent = $project->taskProgressPercent();
                            @endphp

                            <tr
                                wire:key="project-row-{{ $project->id }}"
                                class="border-t border-ghost hover:bg-surface-low/60 transition-colors duration-150"
                            >
                                <th scope="row" class="py-3.5 px-5 font-normal">
                                    <a
                                        href="{{ route('projects.show', ['project' => $project]) }}"
                                        wire:navigate
                                        class="flex items-center gap-3 font-headline font-semibold text-sm text-ink hover:text-accent-content focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded-lg"
                                    >
                                        <x-projects.icon-project
                                            :project="$project"
                                            containerClass="w-8! h-8! rounded-lg shrink-0"
                                            iconClass="size-4"
                                        />
                                        <span class="truncate">{{ $project->name }}</span>
                                    </a>
                                </th>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span
                                        data-project-type-tag
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ ProjectType::badgeClassesFor($project->type) }}"
                                    >
                                        {{ $project->type }}
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3 min-w-36">
                                        <div class="w-20 bg-surface-highest rounded-full h-1.5 overflow-hidden shrink-0">
                                            <div
                                                class="{{ ProjectStatus::progressBarClassFor($project->status) }} h-1.5 rounded-full"
                                                style="width: {{ $progressPercent }}%"
                                            ></div>
                                        </div>
                                        <span class="text-xs font-medium text-ink-muted">
                                            {{ $doneTasks }} of {{ $totalTasks }} tasks
                                        </span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <span
                                            class="size-2 rounded-full {{ ProjectStatus::dotClassFor($project->status) }}"
                                            aria-hidden="true"
                                        ></span>
                                        <span class="text-xs font-medium text-ink-muted">
                                            {{ ProjectStatus::labelFor($project->status) }}
                                        </span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-5 text-xs font-medium text-ink-muted whitespace-nowrap">
                                    {{ $project->due_at ? Carbon::parse($project->due_at)->format('M d, Y') : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
