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

            <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient text-center px-6 py-16 space-y-4">
                <div class="size-12 rounded-2xl bg-surface-low flex items-center justify-center mx-auto text-ink-subtle">
                    <flux:icon.book-open class="size-6" />
                </div>

                <div class="space-y-1.5 max-w-md mx-auto">
                    <h2 class="font-headline font-bold text-lg text-ink">
                        {{ __('No notebooks yet') }}
                    </h2>
                    <p class="text-sm text-ink-muted">
                        {{ __('Group project documentation, architecture notes, and guides in multi-page notebooks.') }}
                    </p>
                </div>

                <div class="pt-2 flex flex-col items-center gap-3">
                    <livewire:projects.notebooks.create-notebook :project="$project" button-label="Create your first notebook" />

                    <p class="text-xs text-ink-subtle">
                        {{ __('or import your repository docs with') }}
                        <code class="px-1.5 py-0.5 rounded bg-surface-low text-ink-muted font-mono">funwork:sync-docs</code>
                    </p>
                </div>
            </div>
        </div>
    @else
        <livewire:projects.notebooks.show-notebooks :project="$project" />
    @endif
</div>
