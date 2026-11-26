<?php

use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')]
#[Title('Notebooks')]
class extends Component
{
    public ?Project $project = null;

    public ?int $projectId = null;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->projectId = $project->id;
    }

    public function with(): array
    {
        return [
            'project' => $this->project,
            'hasNotebooks' => $this->project->notebooks()->exists(),
        ];
    }

    #[On('created-notebook')]
    public function refreshNotebooks(): void
    {
        // Livewire re-renders with updated notebook state
    }
};
?>

<div class="max-w-7xl mx-auto w-full py-2 sm:py-4">
    @if (! $hasNotebooks)
        <div class="space-y-6">
            <header class="min-w-0">
                <div class="flex items-center gap-2.5">
                    <h1 class="font-headline font-bold text-2xl sm:text-3xl tracking-tight text-ink">
                        {{ __('Notebooks') }}
                    </h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold text-ink-muted bg-surface-high/70 rounded-full tabular-nums">
                        0
                    </span>
                </div>
                <p class="text-sm text-ink-muted mt-1">
                    {{ __('Project documentation, architecture notes, and guides.') }}
                </p>
            </header>

            <div class="max-w-2xl mx-auto bg-surface-card rounded-2xl border border-ghost shadow-ambient px-6 py-10 sm:p-10 text-center space-y-6">
                <div class="space-y-4">
                    <div class="size-14 rounded-2xl bg-brand-tint text-accent-content flex items-center justify-center mx-auto" aria-hidden="true">
                        <flux:icon.book-open class="size-7" />
                    </div>

                    <div class="space-y-2 max-w-lg mx-auto">
                        <h2 class="font-headline font-bold text-xl sm:text-2xl text-ink">
                            {{ __('No notebooks yet') }}
                        </h2>
                        <p class="text-sm text-ink-muted leading-relaxed">
                            {{ __("Notebooks hold your project's docs, notes and specs. Write them here, or keep them in sync with the docs folder of your GitHub repository.") }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-left">
                    <div class="rounded-xl bg-surface-low/60 border border-ghost p-5 flex flex-col justify-between gap-5">
                        <div>
                            <div class="size-9 rounded-lg bg-surface-card border border-ghost flex items-center justify-center text-ink" aria-hidden="true">
                                <flux:icon.pencil-square class="size-4.5" />
                            </div>
                            <h3 class="font-headline font-semibold text-base text-ink mt-3 mb-1">
                                {{ __('Write in FunWork') }}
                            </h3>
                            <p class="text-xs text-ink-muted leading-relaxed">
                                {{ __('Create a notebook and start writing pages.') }}
                            </p>
                        </div>

                        <livewire:projects.notebooks.create-notebook
                            :project="$project"
                            button-label="New notebook"
                            button-variant="outline"
                            button-class="w-full justify-center bg-surface-card! border-ghost!"
                        />
                    </div>

                    <div class="rounded-xl bg-surface-low/60 border border-ghost p-5 flex flex-col justify-between gap-5">
                        <div>
                            <div class="size-9 rounded-lg bg-surface-card border border-ghost flex items-center justify-center text-ink" aria-hidden="true">
                                <svg class="size-5 fill-current" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" />
                                </svg>
                            </div>
                            <h3 class="font-headline font-semibold text-base text-ink mt-3 mb-1">
                                {{ __('Connect a repository') }}
                            </h3>
                            <p class="text-xs text-ink-muted leading-relaxed">
                                {{ __('Every push to your branch updates the notebook automatically.') }}
                            </p>
                        </div>

                        <flux:button
                            variant="primary"
                            :href="route('project.settings', $project).'#repositories'"
                            wire:navigate
                            class="w-full justify-center max-md:min-h-11"
                        >
                            {{ __('Connect repository') }}
                        </flux:button>
                    </div>
                </div>

                <div>
                    <a
                        href="{{ route('project.settings', $project).'#repositories' }}"
                        wire:navigate
                        class="inline-flex items-center justify-center gap-1 text-xs font-medium text-ink-muted hover:text-accent-content transition-colors max-md:min-h-11 px-2 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        <span>{{ __('How repository sync works') }}</span>
                        <flux:icon name="chevron-right" variant="micro" class="size-3.5" aria-hidden="true" />
                    </a>
                </div>
            </div>
        </div>
    @else
        <livewire:projects.notebooks.show-notebooks :project="$project" />
    @endif
</div>
