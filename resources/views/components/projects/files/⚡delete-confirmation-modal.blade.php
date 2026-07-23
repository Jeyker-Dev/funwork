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
    <flux:heading size="lg" class="mb-4">Confirmar Eliminación</flux:heading>
    
    <div class="mb-6 text-gray-600 dark:text-gray-400">
        ¿Estás seguro de que deseas eliminar 
        @if($selectedFoldersCount > 0)
            <strong>{{ $selectedFoldersCount }} carpeta(s)</strong>
        @endif
        @if($selectedFoldersCount > 0 && $selectedFilesCount > 0)
            y 
        @endif
        @if($selectedFilesCount > 0)
            <strong>{{ $selectedFilesCount }} archivo(s)</strong>
        @endif
        ? Esta acción no se puede deshacer.
    </div>

    <div class="flex gap-2 justify-end mt-4">
        <flux:modal.close>
            <flux:button variant="ghost" wire:loading.attr="disabled" wire:target="delete">Cancelar</flux:button>
        </flux:modal.close>
        <flux:button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">
            <span wire:loading.remove wire:target="delete">Eliminar</span>
            <span wire:loading wire:target="delete">Procesando...</span>
        </flux:button>
    </div>
</flux:modal>
