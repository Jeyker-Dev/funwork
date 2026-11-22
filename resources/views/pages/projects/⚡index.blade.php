<?php

use App\Actions\Projects\GetProjectsOverviewAction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts.home', ['title' => 'Projects'])]
class extends Component {
    public string $search = '';

    public function with(GetProjectsOverviewAction $getProjectsOverview): array
    {
        $overview = $getProjectsOverview(auth()->user());

        return [
            'overview' => $overview,
            'projects' => $overview->projects,
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

<div>
    <flux:main class="bg-surface">
        <div class="max-w-7xl mx-auto w-full py-4 sm:py-6 space-y-10">
            <header class="space-y-1.5">
                <h1 class="font-headline text-3xl sm:text-4xl font-extrabold tracking-tight text-ink">
                    {{ $overview->greeting }}
                </h1>
                <p class="text-sm sm:text-base font-medium text-ink-muted">
                    {{ $overview->summaryText() }}
                </p>
            </header>

            @if($projects->isEmpty())
                <section class="bg-surface-card rounded-2xl border border-ghost shadow-ambient px-6 py-14 text-center flex flex-col items-center justify-center max-w-xl mx-auto">
                    <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-brand-tint text-brand mb-5">
                        <flux:icon name="folder-plus" variant="solid" class="size-7" />
                    </div>

                    <h2 class="font-headline font-bold text-xl text-ink mb-2">
                        No projects yet
                    </h2>

                    <p class="text-sm text-ink-muted max-w-md mb-6">
                        Create your first project to organize tasks, notebooks, and files in one place.
                    </p>

                    <livewire:projects.create-project elementName=""/>
                </section>
            @else
                @if($overview->continueProject && $search === '')
                    <x-projects.continue-card :continue="$overview->continueProject" />
                @endif

                <livewire:projects.show-projects/>
            @endif
        </div>
    </flux:main>
</div>
