<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new #[Layout('layouts.app')]
class extends Component {
    use WithFileUploads;

    public Project $project;

    public string $newFolderName = '';
    public $uploadedFiles = [];

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function createFolder()
    {
        $this->validate([
            'newFolderName' => 'required|string|max:255',
        ]);

        $this->project->folders()->create([
            'name' => $this->newFolderName,
            'created_by' => Auth::id(),
            // parent_id remains null for root
        ]);

        $this->reset('newFolderName');
        Flux::modal('create-folder')->close();
    }

    public function uploadFiles()
    {
        $this->validate([
            'uploadedFiles.*' => 'required|file|max:10240', // 10MB max per file
        ]);

        foreach ($this->uploadedFiles as $file) {
            // Extraer metadata ANTES de mover el archivo (porque store() elimina el temporal)
            $name = $file->getClientOriginalName();
            $mime = $file->getMimeType();
            $size = $file->getSize();

            $path = $file->store('projects/'.$this->project->id, 'local');

            $this->project->files()->create([
                'name' => $name,
                'file_path' => $path,
                'disk' => 'local',
                'mime_type' => $mime,
                'size' => $size,
                'created_by' => Auth::id(),
                // folder_id remains null for root files
            ]);
        }

        $this->reset('uploadedFiles');
        Flux::modal('upload-file')->close();
    }

    // Los datos reales se inyectan a la vista usando el método with()
    public function with(): array
    {
        return [
            'folders' => $this->project->folders()->whereNull('parent_id')->get(),
            'files' => $this->project->files()->whereNull('folder_id')->get(),
        ];
    }

    public function render()
    {
        return view('pages.projects.files.⚡index')
            ->layout('layouts.app', [
                'title' => 'Files',
                'project' => $this->project,
            ]);
    }
};
?>

<div>
    <div class="bg-gray-100 min-h-screen rounded-xl">
        
        @if($folders->isNotEmpty() || $files->isNotEmpty())
            <!-- POPULATED STATE -->
            <div>
                <!-- Contextual Action Bar -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <!-- Breadcrumbs -->
                    <div class="flex items-center space-x-2 text-sm text-gray-500 font-medium">
                        <span>Files</span>
                        <flux:icon.chevron-right class="w-4 h-4 text-gray-400" />
                        <span class="text-gray-900 font-semibold">Root</span>
                    </div>

                    <!-- Actions -->
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
                </div>

                <!-- Folders Section -->
                <div class="mb-8">
                    <flux:heading size="lg" class="mb-4">Folders</flux:heading>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        @forelse($folders as $folder)
                            <flux:card wire:key="folder-{{ $folder->id }}" class="relative cursor-pointer hover:border-gray-300 transition-colors bg-white">
                                <div class="absolute top-2 right-2 z-10">
                                    <flux:button icon="ellipsis-vertical" variant="subtle" size="sm" class="text-gray-400 hover:text-gray-900" />
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

                <!-- Recent Files Section -->
                <div>
                    <flux:heading size="lg" class="mb-4">Recent Files</flux:heading>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        @forelse($files as $file)
                            <flux:card wire:key="file-{{ $file->id }}" class="relative cursor-pointer hover:border-gray-300 transition-colors bg-white p-0 overflow-hidden flex flex-col">
                                <div class="absolute top-2 right-2 z-20">
                                    <flux:button icon="ellipsis-vertical" variant="subtle" size="sm" class="bg-white/80 backdrop-blur-sm shadow-sm text-gray-500 hover:text-gray-900" />
                                </div>
                                
                                <!-- File Preview Area -->
                                <div class="h-32 bg-gray-50 border-b border-gray-100 flex items-center justify-center relative overflow-hidden">
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
                                <div class="p-4 flex gap-3 items-start relative z-10 bg-white">
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
                </div>
            </div>
        @else
            <!-- EMPTY STATE -->
            <div class="flex flex-col items-center justify-center py-20 px-4 h-[70vh]">
                <!-- Large Gray SVG Folder Wrapper -->
                <div class="bg-gray-100 w-32 h-32 rounded-3xl flex items-center justify-center mb-8 shadow-inner">
                    <flux:icon.folder variant="outline" class="w-16 h-16 text-gray-400 stroke-[1.5]" />
                </div>
                
                <flux:heading size="xl" class="mb-3 text-gray-900 font-bold">No files or folders yet</flux:heading>
                
                <p class="text-gray-500 text-center max-w-sm mb-8 text-sm md:text-base">
                    Your workspace is a blank canvas. Start organizing your projects by creating a folder or uploading your first file.
                </p>
                
                <div class="flex gap-4">
                    <flux:modal.trigger name="upload-file">
                        <flux:button variant="primary" icon="arrow-up-tray">Upload File</flux:button>
                    </flux:modal.trigger>
                    
                    <flux:modal.trigger name="create-folder">
                        <flux:button variant="outline" icon="folder-plus">Create Folder</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>
        @endif

        <!-- Modal Crear Carpeta -->
        <flux:modal name="create-folder" class="min-w-[22rem]">
            <form wire:submit="createFolder">
                <flux:heading size="lg" class="mb-4">Create New Folder</flux:heading>
                
                <flux:input wire:model="newFolderName" label="Folder Name" placeholder="e.g. Assets" class="mb-2" />
                <flux:error name="newFolderName" class="mb-4" />
                
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
                    <input type="file" multiple wire:model="uploadedFiles" class="block w-full text-sm text-gray-500 mt-2
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-md file:border-0
                        file:text-sm file:font-semibold
                        file:bg-orange-50 file:text-orange-700
                        hover:file:bg-orange-100" />
                </div>
                <flux:error name="uploadedFiles" />
                <flux:error name="uploadedFiles.*" />
                
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

    </div>
</div>
