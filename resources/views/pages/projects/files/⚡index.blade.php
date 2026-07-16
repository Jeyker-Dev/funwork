<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new #[Layout('layouts.app')]
#[Title('Files')]
class extends Component {
    use WithFileUploads, WithPagination;

    public ?Project $project = null;

    public \App\Livewire\Forms\FolderForm $folderForm;
    public \App\Livewire\Forms\UploadFileForm $uploadForm;

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

    public function createFolder()
    {
        $this->folderForm->setContext($this->project, $this->currentFolderId);
        $this->folderForm->store();
    }

    public function uploadFiles()
    {
        $this->uploadForm->setContext($this->project, $this->currentFolderId);
        $this->uploadForm->store();
    }

    // Los datos reales se inyectan a la vista usando el método with()
    public function with(): array
    {
        $breadcrumbs = [];
        if ($this->currentFolderId) {
            $folder = \App\Models\Folder::find($this->currentFolderId);
            $path = [];
            while($folder) {
                array_unshift($path, $folder);
                $folder = $folder->parent;
            }
            $breadcrumbs = $path;
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
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <!-- Breadcrumbs -->
                <div class="flex items-center space-x-2 text-sm text-gray-500 font-medium">
                    <button wire:click="navigateToRoot" class="hover:text-gray-900 transition-colors {{ empty($breadcrumbs) ? 'text-gray-900 font-semibold' : '' }}">Files</button>

                    @if(!empty($breadcrumbs))
                        <flux:icon.chevron-right class="w-4 h-4 text-gray-400" />

                        @if(count($breadcrumbs) > 3)
                            <button wire:click="navigateToFolder({{ $breadcrumbs[0]->id }})" class="hover:text-gray-900 transition-colors">{{ $breadcrumbs[0]->name }}</button>
                            <flux:icon.chevron-right class="w-4 h-4 text-gray-400" />
                            <span class="text-gray-400">...</span>
                            <flux:icon.chevron-right class="w-4 h-4 text-gray-400" />
                            <button wire:click="navigateToFolder({{ $breadcrumbs[count($breadcrumbs)-2]->id }})" class="hover:text-gray-900 transition-colors">{{ $breadcrumbs[count($breadcrumbs)-2]->name }}</button>
                            <flux:icon.chevron-right class="w-4 h-4 text-gray-400" />
                            <span class="text-gray-900 font-semibold">{{ $breadcrumbs[count($breadcrumbs)-1]->name }}</span>
                        @else
                            @foreach($breadcrumbs as $index => $crumb)
                                @if($index === count($breadcrumbs) - 1)
                                    <span class="text-gray-900 font-semibold">{{ $crumb->name }}</span>
                                @else
                                    <button wire:click="navigateToFolder({{ $crumb->id }})" class="hover:text-gray-900 transition-colors">{{ $crumb->name }}</button>
                                    <flux:icon.chevron-right class="w-4 h-4 text-gray-400" />
                                @endif
                            @endforeach
                        @endif
                    @endif
                </div>

                <!-- Actions -->
                @if($folders->isNotEmpty() || $files->isNotEmpty())
                    <div class="flex items-center gap-3">
                        <div class="w-full md:w-64">
                            <flux:input icon="magnifying-glass" placeholder="Search files..." />
                        </div>
                        <div class="flex gap-2 bg-white rounded-lg shadow-sm p-1 border border-gray-200">
                            <button class="p-1.5 rounded-md hover:bg-gray-100 text-gray-600">
                                <flux:icon.squares-2x2 variant="solid" class="w-5 h-5" />
                            </button>
                            <button class="p-1.5 rounded-md hover:bg-gray-100 text-gray-400">
                                <flux:icon.list-bullet class="w-5 h-5" />
                            </button>
                        </div>
                        <flux:modal.trigger name="create-folder">
                            <flux:button icon="folder-plus" variant="outline">New Folder</flux:button>
                        </flux:modal.trigger>
                        <flux:modal.trigger name="upload-file">
                            <flux:button icon="document-plus" variant="primary">New File</flux:button>
                        </flux:modal.trigger>
                    </div>
                @endif
            </div>
            @endif

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
                            <flux:card wire:key="folder-{{ $folder->id }}" wire:click="navigateToFolder({{ $folder->id }})"
                                @class([
                                    'relative cursor-pointer transition-colors group',
                                    'border-orange-500 bg-orange-50 ring-1 ring-orange-500' => in_array('folder_' . $folder->id, $selectedIds),
                                    'hover:border-gray-300 bg-white' => !in_array('folder_' . $folder->id, $selectedIds),
                                ])>
                                <div class="absolute top-2 left-2 z-20">
                                    <input type="checkbox" wire:click.stop wire:model.live="selectedIds" value="folder_{{ $folder->id }}"
                                        class="rounded border-gray-300 text-orange-600 focus:ring-orange-600 cursor-pointer w-4 h-4
                                        {{ in_array('folder_' . $folder->id, $selectedIds) ? 'opacity-100' : 'opacity-0 group-hover:opacity-100' }} transition-opacity">
                                </div>
                                <div class="absolute top-2 right-2 z-10">
                                    <button wire:click.stop class="p-1.5 rounded-md text-gray-400 hover:text-gray-900 hover:bg-white/50 transition-colors">
                                        <flux:icon.ellipsis-vertical class="w-5 h-5" />
                                    </button>
                                </div>
                                <div class="mb-4 text-orange-600 bg-orange-50 w-10 h-10 rounded-lg flex items-center justify-center">
                                    <flux:icon.folder variant="solid" class="w-6 h-6" />
                                </div>
                                <div class="font-semibold text-gray-900">{{ $folder->name }}</div>
                                <div class="text-sm text-gray-500 mt-1">{{ $folder->files()->count() }} items</div>
                            </flux:card>
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
                            <flux:card wire:key="file-{{ $file->id }}"
                                @class([
                                    'relative cursor-pointer transition-colors p-0 overflow-hidden flex flex-col group',
                                    'border-orange-500 ring-1 ring-orange-500 bg-orange-50' => in_array('file_' . $file->id, $selectedIds),
                                    'hover:border-gray-300 bg-white' => !in_array('file_' . $file->id, $selectedIds),
                                ])>
                                <div class="absolute top-2 left-2 z-30">
                                    <input type="checkbox" wire:model.live="selectedIds" value="file_{{ $file->id }}"
                                        class="rounded border-gray-300 text-orange-600 focus:ring-orange-600 cursor-pointer w-4 h-4
                                        {{ in_array('file_' . $file->id, $selectedIds) ? 'opacity-100' : 'opacity-0 group-hover:opacity-100' }} transition-opacity bg-white/80 backdrop-blur-sm">
                                </div>
                                <div class="absolute top-2 right-2 z-20">
                                    <button class="p-1.5 rounded-md text-gray-500 hover:text-gray-900 bg-white/80 backdrop-blur-sm shadow-sm hover:bg-white transition-colors">
                                        <flux:icon.ellipsis-vertical class="w-5 h-5" />
                                    </button>
                                </div>

                                <!-- File Preview Area -->
                                <div class="h-32 border-b border-gray-100 flex items-center justify-center relative overflow-hidden {{ in_array('file_' . $file->id, $selectedIds) ? 'bg-orange-50' : 'bg-gray-50' }}">
                                    @if(str_contains($file->mime_type, 'image'))
                                        <div class="absolute inset-0 bg-gradient-to-tr from-orange-200/40 to-white z-0"></div>
                                        <flux:icon.photo class="w-12 h-12 text-orange-400 relative z-10" />
                                    @elseif(str_contains($file->mime_type, 'pdf'))
                                        <div class="text-red-500 bg-red-50 p-3 rounded-xl shadow-sm relative z-10">
                                            <flux:icon.document-text variant="solid" class="w-10 h-10" />
                                        </div>
                                    @else
                                        <div class="text-blue-500 bg-blue-50 p-3 rounded-xl shadow-sm relative z-10">
                                            <flux:icon.document class="w-10 h-10" />
                                        </div>
                                    @endif
                                </div>

                                <!-- File Metadata -->
                                <div class="p-4 flex gap-3 items-start relative z-10 transition-colors {{ in_array('file_' . $file->id, $selectedIds) ? 'bg-orange-50' : 'bg-white' }}">
                                    <div class="mt-1 {{ str_contains($file->mime_type, 'image') ? 'text-orange-500' : (str_contains($file->mime_type, 'pdf') ? 'text-red-500' : 'text-blue-500') }}">
                                        @if(str_contains($file->mime_type, 'image'))
                                            <flux:icon.photo class="w-5 h-5" />
                                        @elseif(str_contains($file->mime_type, 'pdf'))
                                            <flux:icon.document-text class="w-5 h-5" />
                                        @else
                                            <flux:icon.document class="w-5 h-5" />
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-gray-900 truncate">{{ $file->name }}</div>
                                        <div class="text-xs text-gray-500 mt-1 uppercase tracking-wide">
                                            {{ explode('/', $file->mime_type)[1] ?? 'File' }} • {{ number_format($file->size / 1024 / 1024, 2) }} MB
                                        </div>
                                    </div>
                                </div>
                            </flux:card>
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
                <div x-data x-show="$wire.selectedIds.length > 0" x-cloak
                     @download-completed.window="window.Flux && Flux.toast({ heading: '¡Descarga completada!', variant: 'success' })"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-8"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 translate-y-8"
                     class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 flex items-center bg-gray-900 text-white rounded-full shadow-xl px-4 py-2 gap-4">

                    <div class="flex items-center gap-2 pr-4 border-r border-gray-700">
                        <div class="bg-orange-500 rounded p-1 flex items-center justify-center h-5 w-5">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium" x-text="$wire.selectedIds.length + ' item(s) seleccionado(s)'"></span>
                    </div>

                    <div class="flex items-center gap-1">
                        <button wire:click="moveSelected" class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium hover:bg-gray-800 rounded-full transition-colors">
                            <flux:icon.arrows-right-left class="w-4 h-4" /> Mover
                        </button>
                        <button 
                            x-on:click="if(! $el.disabled) { window.Flux && Flux.toast({ heading: 'Preparando descarga...', variant: 'info' }) }"
                            wire:click="downloadSelected" 
                            wire:loading.attr="disabled"
                            wire:target="downloadSelected"
                            class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium hover:bg-gray-800 rounded-full transition-colors disabled:opacity-50 disabled:cursor-wait"
                        >
                            <flux:icon.arrow-down-tray wire:loading.remove wire:target="downloadSelected" class="w-4 h-4" />
                            <flux:icon.loading wire:loading wire:target="downloadSelected" class="w-4 h-4 animate-spin" />
                            <span wire:loading.remove wire:target="downloadSelected">Descargar</span>
                            <span wire:loading wire:target="downloadSelected">Procesando...</span>
                        </button>
                        <flux:modal.trigger name="delete-confirmation">
                            <button class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-red-400 hover:bg-gray-800 rounded-full transition-colors">
                                <flux:icon.trash class="w-4 h-4" /> Eliminar
                            </button>
                        </flux:modal.trigger>
                    </div>

                    <div class="pl-2">
                        <button wire:click="clearSelection" class="p-1.5 text-gray-400 hover:text-white hover:bg-gray-800 rounded-full transition-colors">
                            <flux:icon.x-mark class="w-5 h-5" />
                        </button>
                    </div>
                </div>
                </div>
            @else
                <!-- EMPTY STATE -->
                <div class="flex flex-col items-center justify-center py-10 px-4 h-[50vh]">
                <!-- Large Gray SVG Folder Wrapper -->
                <div class="bg-gray-100 w-32 h-32 rounded-3xl flex items-center justify-center mb-8 shadow-inner">
                    <flux:icon.folder variant="outline" class="w-16 h-16 text-gray-400 stroke-[1.5]" />
                </div>

                <x-title-text 
                    title="No Files or Folders," 
                    highlight="Yet."
                    description="Your workspace is a blank canvas. Start organizing your projects by creating a folder or uploading your first file." 
                    class="text-center flex flex-col items-center mb-8" 
                />

                <div class="flex gap-4">
                    <flux:modal.trigger name="upload-file">
                        <flux:button variant="primary" icon="arrow-up-tray">Upload File</flux:button>
                    </flux:modal.trigger>

                    <flux:modal.trigger name="create-folder">
                        <flux:button variant="outline" icon="folder-plus">Create Folder</flux:button>
                    </flux:modal.trigger>
                </div>
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
    </div>

        <!-- Modal Crear Carpeta -->
        <flux:modal name="create-folder" class="min-w-[22rem]">
            <form wire:submit="createFolder">
                <flux:heading size="lg" class="mb-4">Create New Folder</flux:heading>

                <flux:input wire:model="folderForm.name" label="Folder Name" placeholder="e.g. Assets" class="mb-2" />
                <flux:error name="folderForm.name" class="mb-4" />

                <div class="flex gap-2 justify-end mt-4">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Create Folder</flux:button>
                </div>
            </form>
        </flux:modal>

        <!-- Modal Subir Archivos -->
        <flux:modal name="upload-file" class="min-w-[22rem]">
            <form wire:submit="uploadFiles">
                <flux:heading size="lg" class="mb-4">Upload Files</flux:heading>

                <div class="mb-2">
                    <flux:label>Select files</flux:label>
                    <input type="file" multiple wire:model="uploadForm.files" class="block w-full text-sm text-gray-500 mt-2
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-md file:border-0
                        file:text-sm file:font-semibold
                        file:bg-orange-50 file:text-orange-700
                        hover:file:bg-orange-100" />
                </div>
                <flux:error name="uploadForm.files" />
                <flux:error name="uploadForm.files.*" />

                <div class="flex gap-2 justify-end mt-6">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <!-- Show loading state on upload button -->
                    <flux:button type="submit" variant="primary">
                        <span wire:loading.remove wire:target="uploadFiles">Upload</span>
                        <span wire:loading wire:target="uploadFiles">Uploading...</span>
                    </flux:button>
                </div>
            </form>
        </flux:modal>

        <!-- Modal Confirmar Eliminación -->
        <flux:modal name="delete-confirmation" class="min-w-[22rem]">
            <flux:heading size="lg" class="mb-4">Confirmar Eliminación</flux:heading>
            
            <div class="mb-6 text-gray-600 dark:text-gray-400">
                ¿Estás seguro de que deseas eliminar 
                @if($this->selectedFoldersCount > 0)
                    <strong>{{ $this->selectedFoldersCount }} carpeta(s)</strong>
                @endif
                @if($this->selectedFoldersCount > 0 && $this->selectedFilesCount > 0)
                    y 
                @endif
                @if($this->selectedFilesCount > 0)
                    <strong>{{ $this->selectedFilesCount }} archivo(s)</strong>
                @endif
                ? Esta acción no se puede deshacer.
            </div>

            <div class="flex gap-2 justify-end mt-4">
                <flux:modal.close>
                    <flux:button variant="ghost" wire:loading.attr="disabled" wire:target="deleteSelected">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteSelected" wire:loading.attr="disabled" wire:target="deleteSelected">
                    <span wire:loading.remove wire:target="deleteSelected">Eliminar</span>
                    <span wire:loading wire:target="deleteSelected">Procesando...</span>
                </flux:button>
            </div>
        </flux:modal>

    </div>
</div>
