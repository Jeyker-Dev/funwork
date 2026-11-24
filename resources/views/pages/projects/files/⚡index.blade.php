<?php

use App\Actions\Files\DeleteSelectionAction;
use App\Actions\Files\DownloadFilesZipAction;
use App\Enums\FileCategory;
use App\Livewire\Forms\UploadFileForm;
use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Support\DateHelper;
use App\Support\QueryHelper;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')]
#[Title('Files')]
class extends Component
{
    use WithFileUploads, WithPagination;

    public ?Project $project = null;

    public array $selectedIds = [];

    #[Url(history: true)]
    public ?int $currentFolderId = null;

    #[Url(history: true)]
    public string $searchQuery = '';

    #[Url(as: 'type', except: '')]
    public string $categoryFilter = '';

    #[Url(except: 'grid')]
    public string $view = 'grid';

    public UploadFileForm $uploadForm;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;

        if (! in_array($this->view, ['grid', 'list'], true)) {
            $this->view = 'grid';
        }

        if ($this->categoryFilter !== '' && FileCategory::tryFrom($this->categoryFilter) === null) {
            $this->categoryFilter = '';
        }
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    public function updatedSearchQuery(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function selectCategory(string $category = ''): void
    {
        if ($category !== '' && FileCategory::tryFrom($category) === null) {
            return;
        }

        $this->categoryFilter = $category;
        $this->resetPage();
        $this->clearSelection();
    }

    public function setView(string $view): void
    {
        if (! in_array($view, ['grid', 'list'], true)) {
            return;
        }

        $this->view = $view;
    }

    public function resetFilters(): void
    {
        $this->categoryFilter = '';
        $this->searchQuery = '';
        $this->resetPage();
        $this->clearSelection();
    }

    public function toggleSelectAllFolders(): void
    {
        $allFolderIds = $this->project->folders()
            ->where('parent_id', $this->currentFolderId)
            ->pluck('id')
            ->map(fn ($id): string => 'folder_'.$id)
            ->toArray();

        $currentlySelectedFolders = array_filter($this->selectedIds, fn ($id): bool => str_starts_with($id, 'folder_'));

        if (count($currentlySelectedFolders) === count($allFolderIds)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $allFolderIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $allFolderIds)));
        }
    }

    public function toggleSelectAllFiles(): void
    {
        $allFileIds = $this->project->files()
            ->where('folder_id', $this->currentFolderId)
            ->pluck('id')
            ->map(fn ($id): string => 'file_'.$id)
            ->toArray();

        $currentlySelectedFiles = array_filter($this->selectedIds, fn ($id): bool => str_starts_with($id, 'file_'));

        if (count($currentlySelectedFiles) === count($allFileIds)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $allFileIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $allFileIds)));
        }
    }

    #[Computed]
    public function selectedFoldersCount(): int
    {
        return count(array_filter($this->selectedIds, fn ($id): bool => str_starts_with($id, 'folder_')));
    }

    #[Computed]
    public function selectedFilesCount(): int
    {
        return count(array_filter($this->selectedIds, fn ($id): bool => str_starts_with($id, 'file_')));
    }

    public function setSingleSelectionAndMove(string $id): void
    {
        $this->selectedIds = [$id];
        $this->moveSelected();
    }

    public function setSingleSelectionAndDelete(string $id): void
    {
        $this->selectedIds = [$id];
        Flux::modal('delete-confirmation')->show();
    }

    public function downloadFolder(int $folderId, DownloadFilesZipAction $action)
    {
        $this->selectedIds = ['folder_'.$folderId];

        return $this->downloadSelected($action);
    }

    #[On('download-single-file')]
    public function downloadSingleFile(int $fileId, DownloadFilesZipAction $action)
    {
        $this->selectedIds = ['file_'.$fileId];

        return $this->downloadSelected($action);
    }

    public function downloadSelected(DownloadFilesZipAction $action)
    {
        $this->authorize('view', $this->project);

        if (empty($this->selectedIds)) {
            return null;
        }

        $response = $action($this->project, $this->selectedIds);

        $this->clearSelection();
        $this->dispatch('download-completed');

        return $response;
    }

    #[On('delete-confirmed')]
    public function deleteSelected(DeleteSelectionAction $action): void
    {
        $this->authorize('view', $this->project);

        if (empty($this->selectedIds)) {
            return;
        }

        try {
            $action($this->project, $this->selectedIds);

            $this->clearSelection();

            Flux::modal('delete-confirmation')->close();

            Flux::toast(
                heading: 'Items Deleted',
                text: 'The selected items have been deleted successfully.',
                variant: 'success',
                duration: 3000,
            );
        } catch (ValidationException $e) {
            Flux::toast(
                heading: 'Action failed',
                text: $e->getMessage(),
                variant: 'error',
                duration: 5000,
            );
        }

        Flux::modals()->close();
    }

    public function navigateToFolder(int $folderId): void
    {
        $folder = Folder::findOrFail($folderId);
        $this->authorize('view', $folder);
        abort_if($folder->project_id !== $this->project->id, 403);

        $this->currentFolderId = $folderId;
        $this->resetPage();
        $this->clearSelection();
    }

    public function navigateToRoot(): void
    {
        $this->currentFolderId = null;
        $this->resetPage();
        $this->clearSelection();
    }

    public function saveDroppedFiles(): void
    {
        $this->authorize('create', [ProjectFile::class, $this->project]);

        $this->uploadForm->setContext($this->project, $this->currentFolderId);

        try {
            $this->uploadForm->store();
            $this->dispatch('file-uploaded');
        } catch (ValidationException $e) {
            $this->uploadForm->reset('files');

            Flux::toast(
                heading: 'Upload failed',
                text: $e->getMessage(),
                variant: 'danger',
                duration: 5000,
            );
        }
    }

    #[On('folder-created')]
    #[On('file-uploaded')]
    #[On('item-renamed')]
    public function refresh(): void {}

    public function moveSelected(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $this->dispatch('open-move-modal', selectedIds: $this->selectedIds);
    }

    #[On('items-moved')]
    public function onItemsMoved(): void
    {
        $this->clearSelection();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $allFolders = $this->project->folders()
            ->get(['id', 'parent_id', 'name'])
            ->keyBy('id');

        $breadcrumbs = $this->resolveBreadcrumbs($allFolders);
        $currentFolder = $this->currentFolderId ? $allFolders->get($this->currentFolderId) : null;
        $folderPaths = $this->buildFolderPaths($allFolders);

        $trimmedSearch = trim($this->searchQuery);
        $isSearching = $trimmedSearch !== '';
        $activeCategory = FileCategory::tryFrom($this->categoryFilter);

        $totalFilesCount = $this->project->files()->count();
        $hasAnyProjectItems = $totalFilesCount > 0 || $allFolders->isNotEmpty();

        if ($activeCategory !== null) {
            $folders = collect();
        } elseif ($isSearching) {
            $escaped = QueryHelper::escapeLike($trimmedSearch);
            $folders = $this->project->folders()
                ->withCount(['files', 'children'])
                ->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$escaped.'%'])
                ->orderBy('name')
                ->get();
        } else {
            $folders = $this->project->folders()
                ->withCount(['files', 'children'])
                ->where('parent_id', $this->currentFolderId)
                ->orderBy('name')
                ->get();
        }

        $filesQuery = $this->project->files()->latest();

        if ($isSearching) {
            $escaped = QueryHelper::escapeLike($trimmedSearch);
            $filesQuery->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
        } elseif ($activeCategory === null || $this->currentFolderId !== null) {
            $filesQuery->where('folder_id', $this->currentFolderId);
        }

        if ($activeCategory !== null) {
            $activeCategory->applyFilter($filesQuery);
        }

        $paginatedFiles = $filesQuery->paginate(30);

        $filesArray = collect($paginatedFiles->items())->map(function (ProjectFile $file): array {
            return [
                'id' => $file->id,
                'name' => $file->name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'preview_url' => route('project.files.preview', [$this->project, $file]),
            ];
        })->toArray();

        return [
            'folders' => $folders,
            'files' => $paginatedFiles,
            'filesArray' => $filesArray,
            'breadcrumbs' => $breadcrumbs,
            'folderPaths' => $folderPaths,
            'isSearching' => $isSearching,
            'activeCategory' => $activeCategory,
            'isFiltering' => $isSearching || $activeCategory !== null,
            'totalFilesCount' => $totalFilesCount,
            'hasAnyProjectItems' => $hasAnyProjectItems,
            'dropTargetName' => $currentFolder?->name ?? $this->project->name,
            'maxUploadSizeLabel' => UploadFileForm::maxFileSizeLabel(),
            'userTimezone' => DateHelper::userTimezone(),
        ];
    }

    /**
     * @param  Collection<int, Folder>  $allFolders
     * @return list<Folder>
     */
    private function resolveBreadcrumbs(Collection $allFolders): array
    {
        if ($this->currentFolderId === null) {
            return [];
        }

        $breadcrumbs = [];
        $visited = [];
        $folder = $allFolders->get($this->currentFolderId);

        while ($folder !== null && ! in_array($folder->id, $visited, true)) {
            $visited[] = $folder->id;
            array_unshift($breadcrumbs, $folder);
            $folder = $folder->parent_id !== null ? $allFolders->get($folder->parent_id) : null;
        }

        return $breadcrumbs;
    }

    /**
     * @param  Collection<int, Folder>  $allFolders
     * @return array<int, string>
     */
    private function buildFolderPaths(Collection $allFolders): array
    {
        $paths = [];

        foreach ($allFolders as $folderId => $folder) {
            $segments = [];
            $visited = [];
            $cursor = $folder;

            while ($cursor !== null && ! in_array($cursor->id, $visited, true)) {
                $visited[] = $cursor->id;
                array_unshift($segments, $cursor->name);
                $cursor = $cursor->parent_id !== null ? $allFolders->get($cursor->parent_id) : null;
            }

            $paths[$folderId] = implode(' / ', $segments);
        }

        return $paths;
    }
};
?>

<div
    x-data="{
        isDragging: false,
        dragDepth: 0,
        isUploading: false,
        hasFiles(event) {
            return Array.from(event.dataTransfer?.types ?? []).includes('Files');
        },
        onDragEnter(event) {
            if (!this.hasFiles(event)) return;
            event.preventDefault();
            this.dragDepth++;
            this.isDragging = true;
        },
        onDragOver(event) {
            if (!this.hasFiles(event)) return;
            event.preventDefault();
        },
        onDragLeave(event) {
            if (!this.isDragging) return;
            this.dragDepth = Math.max(0, this.dragDepth - 1);
            if (this.dragDepth === 0 || !event.relatedTarget) {
                this.cancelDrag();
            }
        },
        cancelDrag() {
            this.dragDepth = 0;
            this.isDragging = false;
        },
        onDrop(event) {
            if (!this.hasFiles(event)) return;
            event.preventDefault();
            const files = event.dataTransfer?.files;
            this.cancelDrag();
            if (!files || files.length === 0) return;
            this.isUploading = true;
            $wire.uploadMultiple(
                'uploadForm.files',
                files,
                () => {
                    $wire.saveDroppedFiles().finally(() => {
                        this.isUploading = false;
                    });
                },
                () => {
                    this.isUploading = false;
                    window.Flux && Flux.toast({ heading: 'Upload failed', text: 'Could not upload the dropped files.', variant: 'danger' });
                }
            );
        }
    }"
    x-on:dragenter.window="onDragEnter($event)"
    x-on:dragover.window="onDragOver($event)"
    x-on:dragleave.window="onDragLeave($event)"
    x-on:drop.window="onDrop($event)"
    x-on:keydown.escape.window="cancelDrag()"
    x-on:blur.window="cancelDrag()"
    x-on:dragend.window="cancelDrag()"
>
    <div @class(['max-w-7xl mx-auto w-full py-2 sm:py-4 space-y-6', 'pb-28' => count($selectedIds) > 0])>
        <header class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div class="min-w-0 shrink-0">
                <div class="flex items-center gap-2.5">
                    <h1 class="font-headline font-bold text-2xl sm:text-3xl tracking-tight text-ink">
                        {{ __('Files') }}
                    </h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold text-ink-muted bg-surface-high/70 rounded-full tabular-nums">
                        {{ $totalFilesCount }}
                    </span>
                </div>
                <p class="text-sm text-ink-muted mt-1">
                    {{ __('Project assets, documents, and shared files.') }}
                </p>
            </div>

            @if($hasAnyProjectItems || $currentFolderId !== null || $isFiltering)
                <div class="flex flex-wrap xl:flex-nowrap items-center gap-2 sm:gap-2.5 min-w-0 max-w-full">
                    <nav
                        aria-label="{{ __('Filter files by type') }}"
                        class="flex flex-wrap items-center gap-1 bg-surface-low p-1 rounded-xl border border-ghost max-w-full"
                    >
                        @php
                            $isAllActive = $categoryFilter === '';
                        @endphp
                        <button
                            type="button"
                            data-category-chip="all"
                            wire:click="selectCategory('')"
                            aria-pressed="{{ $isAllActive ? 'true' : 'false' }}"
                            @class([
                                'inline-flex items-center whitespace-nowrap px-3 py-1.5 max-md:min-h-11 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                                'bg-ink text-surface-card shadow-xs dark:bg-zinc-100 dark:text-zinc-900' => $isAllActive,
                                'text-ink-muted hover:text-ink' => ! $isAllActive,
                            ])
                        >
                            {{ __('All') }}
                        </button>

                        @foreach(\App\Enums\FileCategory::cases() as $category)
                            @php
                                $isCategoryActive = $categoryFilter === $category->value;
                            @endphp
                            <button
                                type="button"
                                wire:key="category-chip-{{ $category->value }}"
                                data-category-chip="{{ $category->value }}"
                                wire:click="selectCategory('{{ $category->value }}')"
                                aria-pressed="{{ $isCategoryActive ? 'true' : 'false' }}"
                                @class([
                                    'inline-flex items-center whitespace-nowrap px-3 py-1.5 max-md:min-h-11 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                                    'bg-ink text-surface-card shadow-xs dark:bg-zinc-100 dark:text-zinc-900' => $isCategoryActive,
                                    'text-ink-muted hover:text-ink' => ! $isCategoryActive,
                                ])
                            >
                                {{ __($category->filterLabel()) }}
                            </button>
                        @endforeach
                    </nav>

                    <div class="flex-1 min-w-36 sm:flex-none sm:w-44 xl:w-48">
                        <label for="files-search" class="sr-only">{{ __('Search files') }}</label>
                        <flux:input
                            id="files-search"
                            wire:model.live.debounce.300ms="searchQuery"
                            icon="magnifying-glass"
                            placeholder="{{ __('Search files') }}"
                            aria-label="{{ __('Search files') }}"
                            clearable
                            class="max-md:[&_input]:min-h-11"
                        />
                    </div>

                    <div
                        class="inline-flex items-center bg-surface-high/70 p-0.5 rounded-lg shrink-0"
                        role="group"
                        aria-label="{{ __('Files view mode') }}"
                    >
                        <button
                            type="button"
                            wire:click="setView('grid')"
                            aria-pressed="{{ $view === 'grid' ? 'true' : 'false' }}"
                            aria-label="{{ __('Grid view') }}"
                            title="{{ __('Grid view') }}"
                            @class([
                                'inline-flex items-center justify-center p-1.5 min-h-8 min-w-8 max-md:min-h-11 max-md:min-w-11 rounded-md transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                                'bg-surface-card text-ink shadow-xs' => $view === 'grid',
                                'text-ink-muted hover:text-ink' => $view !== 'grid',
                            ])
                        >
                            <flux:icon.squares-2x2 class="size-4" aria-hidden="true" />
                        </button>

                        <button
                            type="button"
                            wire:click="setView('list')"
                            aria-pressed="{{ $view === 'list' ? 'true' : 'false' }}"
                            aria-label="{{ __('List view') }}"
                            title="{{ __('List view') }}"
                            @class([
                                'inline-flex items-center justify-center p-1.5 min-h-8 min-w-8 max-md:min-h-11 max-md:min-w-11 rounded-md transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                                'bg-surface-card text-ink shadow-xs' => $view === 'list',
                                'text-ink-muted hover:text-ink' => $view !== 'list',
                            ])
                        >
                            <flux:icon.list-bullet class="size-4" aria-hidden="true" />
                        </button>
                    </div>

                    <flux:modal.trigger name="create-folder">
                        <flux:button
                            icon="folder-plus"
                            variant="outline"
                            size="sm"
                            class="bg-surface-card! border-ghost! whitespace-nowrap shrink-0 max-md:min-h-11"
                        >
                            {{ __('New folder') }}
                        </flux:button>
                    </flux:modal.trigger>

                    <flux:modal.trigger name="upload-file">
                        <flux:button
                            icon="arrow-up-tray"
                            variant="primary"
                            size="sm"
                            class="whitespace-nowrap shrink-0 max-md:min-h-11"
                        >
                            {{ __('Upload') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif
        </header>

        @if($currentFolderId !== null && !empty($breadcrumbs))
            <div wire:loading.remove.delay wire:target="navigateToFolder, navigateToRoot">
                <x-projects.files.breadcrumbs :breadcrumbs="$breadcrumbs" />
            </div>
        @endif

        <div wire:loading.remove.delay wire:target="navigateToFolder, navigateToRoot">
            @if(! $hasAnyProjectItems && $currentFolderId === null && ! $isFiltering)
                <x-projects.files.empty-state />
            @elseif($folders->isEmpty() && $files->isEmpty())
                @if($isFiltering)
                    <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient text-center px-6 py-14 space-y-3">
                        <div class="size-11 rounded-2xl bg-surface-low flex items-center justify-center mx-auto text-ink-subtle">
                            <flux:icon.magnifying-glass class="size-5" />
                        </div>
                        <h2 class="font-headline font-bold text-lg text-ink">{{ __('No matching files') }}</h2>
                        <p class="text-sm text-ink-muted">
                            {{ __('No files match the current filter or search.') }}
                        </p>
                        <div class="pt-1">
                            <flux:button variant="outline" size="sm" wire:click="resetFilters" class="max-md:min-h-11 bg-surface-card! border-ghost!">
                                {{ __('Clear filters') }}
                            </flux:button>
                        </div>
                    </div>
                @else
                    <x-projects.files.empty-state :in-folder="true" />
                @endif
            @else
                @if($view === 'list')
                    <x-projects.files.list-table
                        :folders="$folders"
                        :files="$files"
                        :selected-ids="$selectedIds"
                        :folder-paths="$folderPaths"
                        :user-timezone="$userTimezone"
                    />
                @else
                    <div class="space-y-8">
                        @if($folders->isNotEmpty())
                            <section aria-label="{{ __('Folders') }}">
                                <div class="flex items-center justify-between gap-2 mb-3.5">
                                    <h2 class="font-headline font-bold text-base sm:text-lg text-ink">
                                        {{ $isSearching ? __('Folders found') : __('Folders') }}
                                    </h2>
                                    @php
                                        $allFoldersSelected = count(array_filter($selectedIds, fn ($id): bool => str_starts_with($id, 'folder_'))) === $folders->count();
                                    @endphp
                                    <button
                                        type="button"
                                        wire:click="toggleSelectAllFolders"
                                        class="text-xs sm:text-sm font-medium inline-flex items-center gap-1.5 transition-colors max-md:min-h-11 cursor-pointer {{ $allFoldersSelected ? 'text-accent-content' : 'text-ink-muted hover:text-ink' }}"
                                    >
                                        <flux:icon.check-circle class="w-4 h-4" variant="{{ $allFoldersSelected ? 'solid' : 'outline' }}" />
                                        <span>{{ $allFoldersSelected ? __('Deselect all') : __('Select all folders') }}</span>
                                    </button>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                                    @foreach($folders as $folder)
                                        <x-projects.files.folder-card
                                            :folder="$folder"
                                            :is-selected="in_array('folder_'.$folder->id, $selectedIds, true)"
                                            :is-searching="$isSearching"
                                            :folder-path="$folder->parent_id ? ($folderPaths[$folder->parent_id] ?? null) : null"
                                        />
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if($files->isNotEmpty())
                            <section aria-label="{{ __('Files') }}">
                                <div class="flex items-center justify-between gap-2 mb-3.5">
                                    <h2 class="font-headline font-bold text-base sm:text-lg text-ink">
                                        {{ $isFiltering ? __('Files found') : __('Files') }}
                                    </h2>
                                    @php
                                        $allFilesSelected = count(array_filter($selectedIds, fn ($id): bool => str_starts_with($id, 'file_'))) === $files->count();
                                    @endphp
                                    <button
                                        type="button"
                                        wire:click="toggleSelectAllFiles"
                                        class="text-xs sm:text-sm font-medium inline-flex items-center gap-1.5 transition-colors max-md:min-h-11 cursor-pointer {{ $allFilesSelected ? 'text-accent-content' : 'text-ink-muted hover:text-ink' }}"
                                    >
                                        <flux:icon.check-circle class="w-4 h-4" variant="{{ $allFilesSelected ? 'solid' : 'outline' }}" />
                                        <span>{{ $allFilesSelected ? __('Deselect all') : __('Select all files') }}</span>
                                    </button>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                                    @foreach($files as $file)
                                        <x-projects.files.file-card
                                            :file="$file"
                                            :is-selected="in_array('file_'.$file->id, $selectedIds, true)"
                                            :show-folder-path="$isFiltering"
                                            :folder-path="$file->folder_id ? ($folderPaths[$file->folder_id] ?? null) : null"
                                        />
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                @endif

                @if($files->hasPages())
                    <div class="mt-6">
                        <flux:pagination :paginator="$files" />
                    </div>
                @endif
            @endif
        </div>

        <div wire:loading.delay wire:target="navigateToFolder, navigateToRoot" class="w-full" style="display: none;">
            <x-projects.files.skeleton />
        </div>
    </div>

    <x-projects.files.floating-bar />

    <x-projects.files.drop-overlay
        :target-name="$dropTargetName"
        :max-size-label="$maxUploadSizeLabel"
    />

    <livewire:projects.files.create-folder-modal :project="$project" :currentFolderId="$currentFolderId" />
    <livewire:projects.files.upload-file-modal :project="$project" :currentFolderId="$currentFolderId" />
    <livewire:projects.files.delete-confirmation-modal
        :project="$project"
        :selectedFoldersCount="$this->selectedFoldersCount"
        :selectedFilesCount="$this->selectedFilesCount"
    />
    <livewire:projects.files.move-items-modal :project="$project" />
    <livewire:projects.files.rename-item-modal />
    <livewire:projects.files.file-preview-lightbox :files="$filesArray" />
</div>
