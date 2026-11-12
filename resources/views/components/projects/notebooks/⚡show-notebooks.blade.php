<?php

use App\Actions\Notebooks\SyncNotebookToGoogleDocAction;
use App\Models\Notebook;
use App\Models\Project;
use App\Services\Google\GoogleAuthorizationException;
use Flux\Flux;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    public string $activeFilter = 'all';
    public string $sort = 'recent';

    public ?Project $project = null;
    public ?int $changingNotebookId = null;
    public ?int $notebookAreaId = null;

    use WithPagination;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
    }
    public function with(): array
{
    $base = $this->project->notebooks()->with('area');

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

    public function syncGoogleDoc(int $notebookId, SyncNotebookToGoogleDocAction $syncNotebook)
    {
        $notebook = $this->project->notebooks()->findOrFail($notebookId);

        try {
            $syncNotebook($notebook, auth()->user());
        } catch (GoogleAuthorizationException) {
            session()->put('google_return_to', route('project.notebooks', $this->project, absolute: false));

            return $this->redirectRoute('google.redirect');
        } catch (RequestException|ConnectionException $e) {
            report($e);
            Flux::toast('No se pudo sincronizar con Google Docs. Intentá de nuevo.', variant: 'danger');

            return null;
        }

        $this->project->refresh();
        Flux::toast('Project pack sincronizado con Google Docs.', variant: 'success');

        return null;
    }

    public function openChangeAreaModal(int $notebookId): void
    {
        $notebook = $this->project->notebooks()->findOrFail($notebookId);
        $this->authorize('update', $notebook);

        $this->changingNotebookId = $notebook->id;
        $this->notebookAreaId = $notebook->area_id;

        Flux::modal('change-notebook-area')->show();
    }

    public function saveNotebookArea(): void
    {
        $notebook = $this->project->notebooks()->findOrFail($this->changingNotebookId);
        $this->authorize('update', $notebook);

        $this->validate([
            'notebookAreaId' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('areas', 'id')->where('project_id', $this->project->id)],
        ]);

        $notebook->update([
            'area_id' => $this->notebookAreaId ?: null,
        ]);

        Flux::modal('change-notebook-area')->close();
        $this->reset(['changingNotebookId', 'notebookAreaId']);
        Flux::toast('Notebook area updated.', variant: 'success');
    }
}
?>

<div>
    <div class="grid grid-cols-3 gap-4 mb-6">
        @foreach($notebooks as $notebook)
            <flux:card class="space-y-6 cursor-pointer hover:ring-2 hover:ring-zinc-800/10 dark:hover:ring-white/20 transition" wire:click="openNotebook({{ $notebook->id }})">

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ $notebook->name }}</flux:heading>
                        @if($notebook->area)
                            <flux:badge size="sm" :color="$notebook->area->color">{{ $notebook->area->name }}</flux:badge>
                        @endif
                    </div>

                    <div wire:click.stop x-on:click.stop>
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" class="text-gray-500 hover:text-gray-900" />
                            <flux:menu>
                                <flux:menu.item :href="route('project.notebooks.export', [$project, $notebook])" icon="arrow-down-tray">
                                    Descargar .md
                                </flux:menu.item>
                                <flux:menu.item wire:click.stop="openChangeAreaModal({{ $notebook->id }})" icon="tag">
                                    Change area
                                </flux:menu.item>
                                <flux:menu.item wire:click.stop="syncGoogleDoc({{ $notebook->id }})" icon="arrow-path">
                                    Sincronizar con Google Docs
                                </flux:menu.item>
                                @if ($notebook->google_doc_id)
                                    <flux:menu.separator />
                                    <flux:menu.item :href="'https://docs.google.com/document/d/'.$notebook->google_doc_id.'/edit'" target="_blank" icon="arrow-top-right-on-square">
                                        Abrir Google Doc
                                    </flux:menu.item>
                                    <div class="px-2 py-1.5 flex items-center gap-2">
                                        @if ($notebook->google_doc_synced_at)
                                            <flux:text class="text-xs text-zinc-500">
                                                Última sincronización: {{ $notebook->google_doc_synced_at->timezone(auth()->user()->timezone ?? config('app.timezone'))->format('Y-m-d H:i') }}
                                            </flux:text>
                                        @endif
                                        @if ($notebook->hasUnsyncedChanges())
                                            <flux:badge color="amber" size="sm">Cambios sin sincronizar</flux:badge>
                                        @endif
                                    </div>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </div>
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

    <flux:modal name="change-notebook-area" class="w-md">
        <form wire:submit.prevent="saveNotebookArea" class="space-y-6">
            <div>
                <flux:heading size="lg">Change Area</flux:heading>
                <flux:text class="mt-1">Select an area for this notebook.</flux:text>
            </div>

            <flux:select variant="listbox" label="Area" placeholder="No area" wire:model="notebookAreaId">
                <flux:select.option :value="null">No area</flux:select.option>
                @foreach($project->areas as $area)
                    <flux:select.option :value="$area->id">{{ $area->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @error('notebookAreaId')
                <div class="text-xs text-red-500">{{ $message }}</div>
            @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
