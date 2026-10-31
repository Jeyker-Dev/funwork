<?php

use Livewire\Component;
use App\Models\Notebook;
use Livewire\Attributes\On;
use App\Models\Project;
use Livewire\WithPagination;

new class extends Component
{
    public string $activeFilter = 'all';
    public string $sort = 'recent';

    public ?Project $project = null;

    use WithPagination;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
    }
    public function with(): array
{
    $base = $this->project->notebooks();

    $counts = [
        'all' => $this->project->notebooks()->count(),
        'shared' => $this->project->notebooks()->where('is_shared', true)->count(),
        'archived' => $this->project->notebooks()->where('is_archived', true)->count(),
    ];

    if ($this->activeFilter !== 'all') {
        if ($this->activeFilter === 'shared') {
            $base = $base->where('is_shared', true);
        } elseif ($this->activeFilter === 'archived') {
            $base = $base->where('is_archived', true);
        }
    }

    $base = $this->sort === 'recent' ? $base->latest() : $base->oldest();

    return [
        'counts' => $counts,
        'notebooks' => $base->paginate(6),
    ];
}

    #[On('created-notebook')]
    public function refreshNotebooks(): void
    {
        $this->with();
    }
    #[On('notebooks-filter-changed')]
    public function updateFilter(string $filter): void
    {
        $this->activeFilter = $filter;
        $this->resetPage();
    }

    #[On('notebooks-sort-changed')]
    public function updateSort(string $sort): void
    {
        $this->sort = $sort;
        $this->resetPage();
    }

    public function openNotebook(Notebook $notebook)
    {
        $this->authorize('view', $notebook);
        abort_if($notebook->project_id !== $this->project->id, 403);

        $page = $notebook->pages()->orderBy('order', 'asc')->first();

        if (! $page) {
            $this->authorize('create', [\App\Models\NotebookPage::class, $notebook]);

            $page = $notebook->pages()->create([
                'content' => ['body' => ''],
                'order' => 1,
                'created_by' => auth()->id(),
            ]);
        }

        return $this->redirectRoute('project.notebooks.pages.edit', [
            'project' => $this->project,
            'notebook' => $notebook,
            'page' => $page,
        ], navigate: true);
    }
}
?>

<div>
    <div class="grid grid-cols-3 gap-4 mb-6">
        @foreach($notebooks as $notebook)
            <flux:card class="space-y-6 cursor-pointer hover:ring-2 hover:ring-zinc-800/10 dark:hover:ring-white/20 transition" wire:click="openNotebook({{ $notebook->id }})">

                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ $notebook->name }}</flux:heading>

                    <flux:icon.ellipsis-vertical class="size-7" />
                </div>

                <div>
                    <flux:text class="mt-2">{{ $notebook->description }}</flux:text>
                </div>

                <flux:separator />

                <div class="flex items-center justify-between">
                    <flux:text class="mt-2 text-xs!">Modified at 2h</flux:text>

                    <flux:avatar src="https://unavatar.io/x/calebporzio" />
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:pagination :paginator="$notebooks" />
</div>
