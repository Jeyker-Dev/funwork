<?php

use App\Models\Project;
use Livewire\Component;

new class extends Component {
    public Project $project;

    public int $selectedFoldersCount = 0;

    public int $selectedFilesCount = 0;

    public function delete()
    {
        $this->dispatch('delete-confirmed');
    }
}; ?>

<flux:modal name="delete-confirmation" class="min-w-[22rem]">
    <flux:heading size="lg" class="mb-4">Confirm deletion</flux:heading>

    <div class="mb-6 text-ink-muted text-sm">
        Are you sure you want to delete
        @if($selectedFoldersCount > 0)
            <strong class="text-ink">{{ $selectedFoldersCount }} {{ \Illuminate\Support\Str::plural('folder', $selectedFoldersCount) }}</strong>
        @endif
        @if($selectedFoldersCount > 0 && $selectedFilesCount > 0)
            and
        @endif
        @if($selectedFilesCount > 0)
            <strong class="text-ink">{{ $selectedFilesCount }} {{ \Illuminate\Support\Str::plural('file', $selectedFilesCount) }}</strong>
        @endif
        ? This action cannot be undone.
    </div>

    <div class="flex gap-2 justify-end mt-4">
        <flux:modal.close>
            <flux:button variant="ghost" wire:loading.attr="disabled" wire:target="delete" class="max-md:min-h-11">Cancel</flux:button>
        </flux:modal.close>
        <flux:button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete" class="max-md:min-h-11">
            <span wire:loading.remove wire:target="delete">Delete</span>
            <span wire:loading wire:target="delete">Deleting...</span>
        </flux:button>
    </div>
</flux:modal>
