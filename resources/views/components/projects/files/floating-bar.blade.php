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
