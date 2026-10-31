<?php

use App\Actions\Files\MoveSelectionAction;
use App\Models\Folder;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Project $project;

    public array $selectedIds = [];

    public ?int $targetFolderId = null;

    public string $newFolderName = '';

    public bool $isCreatingFolder = false;

    public function mount(Project $project)
    {
        $this->authorize('view', $project);

        $this->project = $project;
    }

    #[On('open-move-modal')]
    public function open(array $selectedIds)
    {
        $this->authorize('view', $this->project);

        $this->selectedIds = $selectedIds;
        $this->targetFolderId = null;
        $this->isCreatingFolder = false;
        $this->newFolderName = '';

        Flux::modal('move-items-modal')->show();
    }

    public function navigateTo(?int $folderId)
    {
        if ($folderId !== null) {
            $folder = Folder::findOrFail($folderId);
            $this->authorize('view', $folder);
            abort_if($folder->project_id !== $this->project->id, 403);
        }

        $this->targetFolderId = $folderId;
        $this->isCreatingFolder = false;
    }

    public function navigateUp()
    {
        if ($this->targetFolderId) {
            $folder = $this->project->folders()->find($this->targetFolderId);
            $this->targetFolderId = $folder ? $folder->parent_id : null;
        }
        $this->isCreatingFolder = false;
    }

    public function toggleCreateFolder()
    {
        $this->isCreatingFolder = ! $this->isCreatingFolder;
        $this->newFolderName = '';
    }

    public function createFolder()
    {
        $this->authorize('create', [Folder::class, $this->project]);

        if ($this->targetFolderId !== null) {
            $parentFolder = Folder::findOrFail($this->targetFolderId);
            $this->authorize('view', $parentFolder);
            abort_if($parentFolder->project_id !== $this->project->id, 403);
        }

        $this->validate([
            'newFolderName' => 'required|string|max:255',
        ], [], ['newFolderName' => 'Folder name']);

        $folder = Folder::create([
            'project_id' => $this->project->id,
            'parent_id' => $this->targetFolderId,
            'created_by' => auth()->id(),
            'name' => $this->newFolderName,
        ]);

        $this->targetFolderId = $folder->id;
        $this->isCreatingFolder = false;
        $this->newFolderName = '';
    }

    public function move(MoveSelectionAction $action)
    {
        $this->authorize('view', $this->project);

        try {
            $action->execute($this->project, $this->selectedIds, $this->targetFolderId);
            Flux::modal('move-items-modal')->close();
            $this->dispatch('items-moved');
            Flux::toast('Items moved successfully.');
        } catch (ValidationException $e) {
            $this->addError('targetFolderId', $e->getMessage());
        }
    }

    public function with(): array
    {
        // Get the current folder's children
        $children = Folder::where('project_id', $this->project->id)
            ->where('parent_id', $this->targetFolderId)
            ->orderBy('name')
            ->get();

        // Get breadcrumbs
        $breadcrumbs = [];
        if ($this->targetFolderId) {
            $current = $this->project->folders()->find($this->targetFolderId);
            if ($current) {
                $breadcrumbs = $current->breadcrumbs; // Assuming this accessor exists
            }
        }

        return [
            'children' => $children,
            'breadcrumbs' => $breadcrumbs,
        ];
    }
}; ?>

<flux:modal name="move-items-modal" class="min-w-[28rem]">
    <flux:heading size="lg" class="mb-4">Move Items</flux:heading>
    
    <div class="mb-4">
        <!-- Breadcrumbs -->
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item wire:click="navigateTo(null)" class="cursor-pointer">Root</flux:breadcrumbs.item>
            @foreach($breadcrumbs as $crumb)
                <flux:breadcrumbs.item wire:click="navigateTo({{ $crumb['id'] }})" class="cursor-pointer">{{ $crumb['name'] }}</flux:breadcrumbs.item>
            @endforeach
        </flux:breadcrumbs>

        <!-- Folder List -->
        <div class="border rounded-lg max-h-60 overflow-y-auto p-2 border-gray-200 dark:border-gray-700">
            @if($targetFolderId)
                <div wire:click="navigateUp" class="flex items-center gap-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded cursor-pointer">
                    <flux:icon.arrow-up-circle variant="outline" class="w-5 h-5 text-gray-500" />
                    <span>..</span>
                </div>
            @endif
            
            @forelse($children as $child)
                <div wire:click="navigateTo({{ $child->id }})" class="flex items-center gap-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded cursor-pointer">
                    <flux:icon.folder variant="outline" class="w-5 h-5 text-blue-500" />
                    <span>{{ $child->name }}</span>
                </div>
            @empty
                @if(!$targetFolderId)
                    <div class="p-2 text-sm text-gray-500">No folders available in root.</div>
                @else
                    <div class="p-2 text-sm text-gray-500">This folder is empty.</div>
                @endif
            @endforelse
        </div>
        
        <flux:error name="targetFolderId" class="mt-2" />
    </div>

    <!-- Inline Folder Creation -->
    <div class="mb-4">
        @if(!$isCreatingFolder)
            <flux:button wire:click="toggleCreateFolder" variant="ghost" size="sm" icon="plus">New Folder</flux:button>
        @else
            <form wire:submit="createFolder" class="flex items-center gap-2 mt-2">
                <flux:input wire:model="newFolderName" placeholder="Folder Name" size="sm" class="flex-1" />
                <flux:button type="submit" variant="primary" size="sm">Create</flux:button>
                <flux:button wire:click="toggleCreateFolder" variant="ghost" size="sm">Cancel</flux:button>
            </form>
            <flux:error name="newFolderName" class="mt-1" />
        @endif
    </div>

    <!-- Actions -->
    <div class="flex gap-2 justify-end mt-4">
        <flux:modal.close>
            <flux:button variant="ghost">Cancel</flux:button>
        </flux:modal.close>
        <flux:button wire:click="move" variant="primary">Move Here</flux:button>
    </div>
</flux:modal>
