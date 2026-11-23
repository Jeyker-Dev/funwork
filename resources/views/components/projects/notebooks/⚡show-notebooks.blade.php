<?php

use App\Actions\Notebooks\SyncNotebookToGoogleDocAction;
use App\Enums\AreaColor;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Services\Google\GoogleAuthorizationException;
use App\Support\DateHelper;
use App\Support\QueryHelper;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?Project $project = null;

    #[Url(except: 'all')]
    public string $area = 'all';

    public string $search = '';

    public ?int $changingNotebookId = null;

    public ?int $notebookAreaId = null;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
    }

    public function setArea(string $area): void
    {
        $this->area = $area;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->area = 'all';
        $this->search = '';
        $this->resetPage();
    }

    #[On('created-notebook')]
    #[On('areas-updated')]
    public function refreshNotebooks(): void
    {
        $this->project->refresh();
    }

    public function with(): array
    {
        $totalCount = $this->project->notebooks()->count();
        $areas = $this->project->areas()->orderBy('position')->get();

        $query = $this->project->notebooks()
            ->with('area')
            ->withCount('pages')
            ->withCount(['pages as repo_docs_count' => fn (Builder $pageQuery) => $pageQuery->whereNotNull('source_path')])
            ->withMax('pages', 'updated_at')
            ->latest('updated_at')
            ->latest('id');

        QueryHelper::applyAreaFilter($query, $this->area);

        $trimmedSearch = trim($this->search);

        if ($trimmedSearch !== '') {
            $like = '%'.QueryHelper::escapeLike($trimmedSearch).'%';

            $query->where(function (Builder $searchQuery) use ($like): void {
                $searchQuery->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("description LIKE ? ESCAPE '!'", [$like]);
            });
        }

        return [
            'totalCount' => $totalCount,
            'areas' => $areas,
            'notebooks' => $query->paginate(15),
        ];
    }

    public function openNotebook(Notebook $notebook)
    {
        $this->authorize('view', $notebook);
        abort_if($notebook->project_id !== $this->project->id, 403);

        $page = $notebook->pages()->orderBy('order')->orderBy('id')->first();

        if (! $page) {
            $this->authorize('create', [NotebookPage::class, $notebook]);

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
            Flux::toast('Could not sync with Google Docs. Please try again.', variant: 'danger');

            return null;
        }

        $this->project->refresh();
        Flux::toast('Notebook synced with Google Docs.', variant: 'success');

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
            'notebookAreaId' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('project_id', $this->project->id)],
        ]);

        $notebook->update([
            'area_id' => $this->notebookAreaId ?: null,
        ]);

        Flux::modal('change-notebook-area')->close();
        $this->reset(['changingNotebookId', 'notebookAreaId']);
        Flux::toast('Notebook area updated.', variant: 'success');
    }
};
?>

<div class="space-y-6">
    <header class="flex flex-col gap-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2.5">
                    <h1 class="font-headline font-bold text-2xl sm:text-3xl tracking-tight text-ink">
                        {{ __('Notebooks') }}
                    </h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold text-ink-muted bg-surface-high/70 rounded-full tabular-nums">
                        {{ $totalCount }}
                    </span>
                </div>
                <p class="text-sm text-ink-muted mt-1">
                    {{ __('Project documentation, architecture notes, and guides.') }}
                </p>
            </div>

            <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 min-w-0">
                <div class="w-full sm:w-64 min-w-0">
                    <label for="notebooks-search" class="sr-only">{{ __('Search notebooks') }}</label>
                    <flux:input
                        id="notebooks-search"
                        wire:model.live="search"
                        icon="magnifying-glass"
                        placeholder="{{ __('Search notebooks') }}"
                        aria-label="{{ __('Search notebooks') }}"
                        clearable
                    />
                </div>

                <livewire:projects.notebooks.create-notebook :project="$project" />
            </div>
        </div>

        @if ($areas->isNotEmpty())
            @php
                $isAllActive = $area === 'all' || $area === '';
                $isNoAreaActive = in_array(strtolower($area), ['no-area', 'none', 'no_area', 'no area', '0'], true);
            @endphp
            <nav aria-label="{{ __('Filter notebooks by area') }}" class="flex flex-wrap items-center gap-1.5">
                <button
                    type="button"
                    wire:click="setArea('all')"
                    aria-pressed="{{ $isAllActive ? 'true' : 'false' }}"
                    @class([
                        'inline-flex items-center px-3 py-1.5 max-md:min-h-11 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                        'bg-ink text-surface-card shadow-xs dark:bg-zinc-100 dark:text-zinc-900' => $isAllActive,
                        'bg-surface-card text-ink-muted hover:text-ink border border-ghost' => ! $isAllActive,
                    ])
                >
                    {{ __('All') }}
                </button>

                @foreach ($areas as $projectArea)
                    @php
                        $isAreaActive = $area === $projectArea->slug;
                    @endphp
                    <button
                        type="button"
                        wire:key="notebook-area-chip-{{ $projectArea->id }}"
                        wire:click="setArea('{{ $projectArea->slug }}')"
                        aria-pressed="{{ $isAreaActive ? 'true' : 'false' }}"
                        @class([
                            'inline-flex items-center gap-1.5 px-3 py-1.5 max-md:min-h-11 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                            'bg-ink text-surface-card shadow-xs dark:bg-zinc-100 dark:text-zinc-900' => $isAreaActive,
                            'bg-surface-card text-ink-muted hover:text-ink border border-ghost' => ! $isAreaActive,
                        ])
                    >
                        <span class="size-1.5 rounded-full shrink-0 {{ AreaColor::dotClassFor($projectArea->color) }}" aria-hidden="true"></span>
                        <span>{{ $projectArea->name }}</span>
                    </button>
                @endforeach

                <button
                    type="button"
                    wire:click="setArea('no-area')"
                    aria-pressed="{{ $isNoAreaActive ? 'true' : 'false' }}"
                    @class([
                        'inline-flex items-center gap-1.5 px-3 py-1.5 max-md:min-h-11 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                        'bg-ink text-surface-card shadow-xs dark:bg-zinc-100 dark:text-zinc-900' => $isNoAreaActive,
                        'bg-surface-card text-ink-muted hover:text-ink border border-ghost' => ! $isNoAreaActive,
                    ])
                >
                    <span class="size-1.5 rounded-full shrink-0 bg-zinc-400" aria-hidden="true"></span>
                    <span>{{ __('No area') }}</span>
                </button>
            </nav>
        @endif
    </header>

    @if ($notebooks->isEmpty())
        <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient text-center px-6 py-14 space-y-3">
            <div class="size-11 rounded-2xl bg-surface-low flex items-center justify-center mx-auto text-ink-subtle">
                <flux:icon.magnifying-glass class="size-5" />
            </div>
            <h2 class="font-headline font-bold text-lg text-ink">{{ __('No matching notebooks') }}</h2>
            <p class="text-sm text-ink-muted">
                {{ __('No notebooks match the current area filter or search.') }}
            </p>
            <div class="pt-1">
                <flux:button variant="outline" size="sm" wire:click="resetFilters" class="max-md:min-h-11 bg-surface-card! border-ghost!">
                    {{ __('Clear filters') }}
                </flux:button>
            </div>
        </div>
    @else
        <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-1.5 sm:p-2 space-y-0.5">
            @foreach ($notebooks as $notebook)
                @php
                    $latestPageUpdatedAt = $notebook->pages_max_updated_at !== null
                        ? Carbon::parse($notebook->pages_max_updated_at)
                        : null;
                    $lastEditedAt = ($latestPageUpdatedAt !== null && ($notebook->updated_at === null || $latestPageUpdatedAt->gt($notebook->updated_at)))
                        ? $latestPageUpdatedAt
                        : $notebook->updated_at;
                    $isFromRepoDocs = (int) $notebook->repo_docs_count > 0;
                    $hasUnsynced = $notebook->hasUnsyncedChanges();
                    $userTimezone = auth()->user()->timezone ?? config('app.timezone');
                    $pageCount = (int) $notebook->pages_count;
                    $pageCountLabel = match ($pageCount) {
                        0 => __('No pages'),
                        1 => __('1 page'),
                        default => "{$pageCount} ".__('pages'),
                    };
                @endphp
                <div
                    wire:key="notebook-row-{{ $notebook->id }}"
                    class="relative group flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 sm:gap-4 px-3.5 sm:px-4 py-3.5 rounded-xl hover:bg-surface-low transition-colors"
                >
                    <div class="flex items-center gap-3.5 min-w-0 flex-1">
                        <div class="size-9 rounded-xl flex items-center justify-center shrink-0 {{ AreaColor::iconTintClassFor($notebook->area?->color) }}" aria-hidden="true">
                            <flux:icon.book-open class="size-4.5" />
                        </div>

                        <button
                            type="button"
                            wire:click="openNotebook({{ $notebook->id }})"
                            class="min-w-0 flex-1 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded-lg cursor-pointer after:absolute after:inset-0"
                        >
                            <span class="flex flex-col md:flex-row md:items-baseline md:gap-3 min-w-0">
                                <span class="text-sm font-semibold text-ink group-hover:text-accent-content transition-colors truncate">
                                    {{ $notebook->name }}
                                </span>
                                @if (filled($notebook->description))
                                    <span class="text-xs sm:text-sm text-ink-muted truncate">
                                        {{ $notebook->description }}
                                    </span>
                                @endif
                            </span>
                        </button>
                    </div>

                    <div class="flex items-center justify-between sm:justify-end gap-3 sm:gap-5 shrink-0 pl-12.5 sm:pl-0 text-xs">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 min-w-0">
                            @if ($notebook->area)
                                <span class="inline-flex items-center gap-1.5 font-medium text-ink-muted">
                                    <span class="size-1.5 rounded-full shrink-0 {{ AreaColor::dotClassFor($notebook->area->color) }}" aria-hidden="true"></span>
                                    <span>{{ $notebook->area->name }}</span>
                                </span>
                            @endif

                            <span class="text-ink-muted tabular-nums whitespace-nowrap">
                                {{ $pageCountLabel }}
                            </span>

                            @if ($isFromRepoDocs)
                                <span class="inline-flex items-center gap-1 text-ink-muted whitespace-nowrap">
                                    <flux:icon.folder class="size-3.5 text-ink-subtle shrink-0" />
                                    <span>{{ __('From repo docs') }}</span>
                                </span>
                            @endif

                            @if ($hasUnsynced)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-300 font-medium whitespace-nowrap">
                                    <span class="size-1.5 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                                    <span>{{ __('Unsynced changes') }}</span>
                                </span>
                            @endif

                            @if ($lastEditedAt)
                                <span class="text-ink-subtle tabular-nums whitespace-nowrap">
                                    {{ __('Edited') }} {{ DateHelper::relativeAge($lastEditedAt) }}
                                </span>
                            @endif
                        </div>

                        <div class="relative z-10 shrink-0" wire:click.stop x-on:click.stop>
                            <flux:dropdown position="bottom" align="end">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="ellipsis-horizontal"
                                    aria-label="{{ __('More options for :name', ['name' => $notebook->name]) }}"
                                    class="text-ink-subtle hover:text-ink max-md:min-h-11 max-md:min-w-11"
                                />
                                <flux:menu>
                                    <flux:menu.item :href="route('project.notebooks.export', [$project, $notebook])" icon="arrow-down-tray">
                                        {{ __('Export as Markdown') }}
                                    </flux:menu.item>
                                    <flux:menu.item wire:click.stop="openChangeAreaModal({{ $notebook->id }})" icon="tag">
                                        {{ __('Change area') }}
                                    </flux:menu.item>
                                    <flux:menu.item wire:click.stop="syncGoogleDoc({{ $notebook->id }})" icon="arrow-path">
                                        {{ __('Sync with Google Docs') }}
                                    </flux:menu.item>
                                    @if ($notebook->google_doc_id)
                                        <flux:menu.separator />
                                        <flux:menu.item
                                            :href="'https://docs.google.com/document/d/'.$notebook->google_doc_id.'/edit'"
                                            target="_blank"
                                            icon="arrow-top-right-on-square"
                                        >
                                            {{ __('Open in Google Docs') }}
                                        </flux:menu.item>
                                        @if ($notebook->google_doc_synced_at)
                                            <div class="px-2.5 py-1.5">
                                                <p class="text-xs text-ink-subtle">
                                                    {{ __('Last synced:') }} {{ $notebook->google_doc_synced_at->timezone($userTimezone)->locale('en')->format('M j, Y H:i') }}
                                                </p>
                                            </div>
                                        @endif
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($notebooks->hasPages())
            <flux:pagination :paginator="$notebooks" />
        @endif
    @endif

    <flux:modal name="change-notebook-area" class="w-[calc(100vw-2rem)] max-w-md">
        <form wire:submit.prevent="saveNotebookArea" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Change area') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Select an area for this notebook.') }}</flux:text>
            </div>

            <flux:select variant="listbox" label="Area" placeholder="No area" wire:model="notebookAreaId">
                <flux:select.option :value="null">No area</flux:select.option>
                @foreach ($areas as $projectArea)
                    <flux:select.option :value="$projectArea->id">{{ $projectArea->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @error('notebookAreaId')
                <div class="text-xs text-danger dark:text-red-400">{{ $message }}</div>
            @enderror

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
