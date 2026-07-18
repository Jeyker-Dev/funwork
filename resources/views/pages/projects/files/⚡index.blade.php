<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new #[Layout('layouts.app')]
#[Title('Files')]
class extends Component {
    use WithFileUploads, WithPagination;

    public ?Project $project = null;

    public array $selectedIds = [];

    #[Url(history: true)]
    public ?int $currentFolderId = null;

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function clearSelection()
    {
        $this->selectedIds = [];
    }

    public function toggleSelectAllFolders()
    {
        $allFolderIds = $this->project->folders()->where('parent_id', $this->currentFolderId)->pluck('id')->map(fn($id) => 'folder_'.$id)->toArray();
        $currentlySelectedFolders = array_filter($this->selectedIds, fn($id) => str_starts_with($id, 'folder_'));

        if (count($currentlySelectedFolders) === count($allFolderIds)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $allFolderIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $allFolderIds)));
        }
    }

    public function toggleSelectAllFiles()
    {
        $allFileIds = $this->project->files()->where('folder_id', $this->currentFolderId)->pluck('id')->map(fn($id) => 'file_'.$id)->toArray();
        $currentlySelectedFiles = array_filter($this->selectedIds, fn($id) => str_starts_with($id, 'file_'));

        if (count($currentlySelectedFiles) === count($allFileIds)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $allFileIds));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $allFileIds)));
        }
    }

    #[Computed]
    public function selectedFoldersCount()
    {
        return count(array_filter($this->selectedIds, fn($id) => str_starts_with($id, 'folder_')));
    }

    #[Computed]
    public function selectedFilesCount()
    {
        return count(array_filter($this->selectedIds, fn($id) => str_starts_with($id, 'file_')));
    }

    public function downloadSelected(\App\Actions\Files\DownloadFilesZipAction $action)
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $response = $action->execute($this->project, $this->selectedIds);
        
        $this->clearSelection();
        $this->dispatch('download-completed');
        
        return $response;
    }

    #[On('delete-confirmed')]
    public function deleteSelected(\App\Actions\Files\DeleteSelectionAction $action)
    {
        if (empty($this->selectedIds)) {
            return;
        }

        try {
            $action->execute($this->project, $this->selectedIds);
            
            $this->clearSelection();
            
            Flux::modal('delete-confirmation')->close();

            Flux::toast(
                heading: 'Items Deleted',
                text: 'The selected items have been deleted successfully.',
                variant: 'success',
                duration: 3000,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            Flux::toast(
                heading: 'Action failed',
                text: $e->getMessage(),
                variant: 'error',
                duration: 5000,
            );
        }

        Flux::modals()->close();
    }

    public function moveSelected()
    {
        // Placeholder for future move logic
    }

    public function navigateToFolder($folderId)
    {
        $this->currentFolderId = $folderId;
        $this->clearSelection();
    }

    public function navigateToRoot()
    {
        $this->currentFolderId = null;
        $this->clearSelection();
    }

    #[On('folder-created')]
    #[On('file-uploaded')]
    public function refreshData()
    {
        // Livewire will automatically re-render the component
    }

    // Los datos reales se inyectan a la vista usando el método with()
    public function with(): array
    {
        $breadcrumbs = [];
        if ($this->currentFolderId) {
            $folder = \App\Models\Folder::find($this->currentFolderId);
            $breadcrumbs = $folder ? $folder->breadcrumbs : [];
        }

        return [
            'folders' => $this->project->folders()->where('parent_id', $this->currentFolderId)->get(),
            'files' => $this->project->files()->where('folder_id', $this->currentFolderId)->latest()->paginate(30),
            'breadcrumbs' => $breadcrumbs,
        ];
    }
};
?>

<div class="h-[calc(100vh-120px)] flex flex-col">
    <div class="bg-gray-100 rounded-xl flex-1 flex flex-col overflow-hidden">
        <div @class(['flex-1 overflow-y-auto', 'pb-32' => count($selectedIds) > 0, 'pb-2' => empty($selectedIds)])>
            <div wire:loading.remove.delay wire:target="navigateToFolder, navigateToRoot">
                @if($currentFolderId !== null || $folders->isNotEmpty() || $files->isNotEmpty())
                    <!-- Contextual Action Bar -->
                    <x-projects.files.action-bar 
                        :breadcrumbs="$breadcrumbs" 
                        :hasItems="$folders->isNotEmpty() || $files->isNotEmpty()" 
                    />
                @endif
            </div>

            @if($folders->isNotEmpty() || $files->isNotEmpty())
                <!-- POPULATED STATE -->
                <div>
                    <!-- Folders Section -->
                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <flux:heading size="lg">Folders</flux:heading>
                            @if($folders->isNotEmpty())
                                @php
                                    $allFoldersSelected = count(array_filter($selectedIds, fn($id) => str_starts_with($id, 'folder_'))) === $folders->count();
                                @endphp
                                <button wire:click="toggleSelectAllFolders" class="text-sm font-medium flex items-center gap-1.5 transition-colors {{ $allFoldersSelected ? 'text-orange-600' : 'text-gray-400 hover:text-gray-900' }}">
                                    <flux:icon.check-circle class="w-4 h-4" variant="{{ $allFoldersSelected ? 'solid' : 'outline' }}" />
                                    {{ $allFoldersSelected ? 'Deselect all' : 'Select all folders' }}
                                </button>
                            @endif
                        </div>
                        <div class="grid grid-cols-[repeat(auto-fill,minmax(250px,1fr))] gap-4">
                            @forelse($folders as $folder)
                                <x-projects.files.folder-card :folder="$folder" :isSelected="in_array('folder_' . $folder->id, $selectedIds)" />
                            @empty
                                <div class="col-span-full text-sm text-gray-500 italic">No folders created yet.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Files Section -->
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <flux:heading size="lg">Files in this location</flux:heading>
                            @if($files->isNotEmpty())
                                @php
                                    $allFilesSelected = count(array_filter($selectedIds, fn($id) => str_starts_with($id, 'file_'))) === $files->count();
                                @endphp
                                <button wire:click="toggleSelectAllFiles" class="text-sm font-medium flex items-center gap-1.5 transition-colors {{ $allFilesSelected ? 'text-orange-600' : 'text-gray-400 hover:text-gray-900' }}">
                                    <flux:icon.check-circle class="w-4 h-4" variant="{{ $allFilesSelected ? 'solid' : 'outline' }}" />
                                    {{ $allFilesSelected ? 'Deselect all' : 'Select all files' }}
                                </button>
                            @endif
                        </div>
                        <div class="grid grid-cols-[repeat(auto-fill,minmax(250px,1fr))] gap-4">
                            @forelse($files as $file)
                                <x-projects.files.file-card :file="$file" :isSelected="in_array('file_' . $file->id, $selectedIds)" />
                            @empty
                                <div class="col-span-full text-sm text-gray-500 italic">No files uploaded yet.</div>
                            @endforelse
                        </div>

                        @if($files->hasPages())
                            <div class="mt-6">
                                {{ $files->links() }}
                            </div>
                        @endif
                    </div>

                    <!-- Floating Action Bar -->
                    <x-projects.files.floating-bar />
                </div>
            @else
                <!-- EMPTY STATE -->
                <x-projects.files.empty-state />
            @endif
        </div>

        <!-- SKELETON LOADER -->
        <div wire:loading.delay wire:target="navigateToFolder, navigateToRoot" class="w-full" style="display: none;">
            <div class="flex flex-col w-full">
                <!-- Contextual Action Bar Skeleton -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div class="h-6 bg-gray-200 rounded w-48 animate-pulse"></div>
                <div class="flex gap-3">
                    <div class="h-10 bg-gray-200 rounded w-64 animate-pulse"></div>
                    <div class="h-10 bg-gray-200 rounded w-24 animate-pulse"></div>
                    <div class="h-10 bg-gray-200 rounded w-32 animate-pulse"></div>
                    <div class="h-10 bg-gray-200 rounded w-32 animate-pulse"></div>
                </div>
            </div>

            <!-- Folders Skeleton -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div class="h-7 bg-gray-200 rounded w-24 animate-pulse"></div>
                </div>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(250px,1fr))] gap-4">
                    @for($i=0; $i<4; $i++)
                        <div class="h-32 bg-gray-200 border border-gray-100 rounded-xl animate-pulse"></div>
                    @endfor
                </div>
            </div>

            <!-- Files Skeleton -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="h-7 bg-gray-200 rounded w-32 animate-pulse"></div>
                </div>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(250px,1fr))] gap-4">
                    @for($i=0; $i<8; $i++)
                        <div class="h-48 bg-gray-200 border border-gray-100 rounded-xl animate-pulse"></div>
                    @endfor
                </div>
            </div>
                </div>
            </div>
        </div>
    
    <!-- Modals -->
    <livewire:projects.files.create-folder-modal :project="$project" :currentFolderId="$currentFolderId" />
    <livewire:projects.files.upload-file-modal :project="$project" :currentFolderId="$currentFolderId" />
    <livewire:projects.files.delete-confirmation-modal 
        :selectedFoldersCount="$this->selectedFoldersCount" 
        :selectedFilesCount="$this->selectedFilesCount" 
    />
</div>
