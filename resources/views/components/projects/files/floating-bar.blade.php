<div x-data x-show="$wire.selectedIds.length > 0" x-cloak
     @download-completed.window="window.Flux && Flux.toast({ heading: 'Download complete', variant: 'success' })"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-6"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-6"
     class="fixed bottom-6 sm:bottom-8 left-1/2 -translate-x-1/2 z-50 max-w-[calc(100vw-2rem)] flex flex-wrap items-center justify-center bg-ink text-surface-card dark:bg-zinc-800 dark:text-zinc-100 border border-ghost rounded-2xl sm:rounded-full shadow-ambient px-4 py-2 gap-2 sm:gap-4">

    <div class="flex items-center gap-2 pr-3 sm:pr-4 border-r border-white/15 shrink-0">
        <div class="bg-brand-strong rounded p-1 flex items-center justify-center h-5 w-5 shrink-0">
            <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <span class="text-sm font-medium whitespace-nowrap" x-text="$wire.selectedIds.length + ' selected'"></span>
    </div>

    <div class="flex items-center gap-1">
        <button type="button" wire:click="moveSelected" class="flex items-center gap-1.5 px-3 py-1.5 max-md:min-h-11 text-sm font-medium hover:bg-white/10 rounded-full transition-colors cursor-pointer whitespace-nowrap">
            <flux:icon.arrows-right-left class="w-4 h-4 shrink-0" />
            <span>Move</span>
        </button>
        <button
            type="button"
            x-on:click="if(! $el.disabled) { window.Flux && Flux.toast({ heading: 'Preparing download...', variant: 'info' }) }"
            wire:click="downloadSelected"
            wire:loading.attr="disabled"
            wire:target="downloadSelected"
            class="flex items-center gap-1.5 px-3 py-1.5 max-md:min-h-11 text-sm font-medium hover:bg-white/10 rounded-full transition-colors disabled:opacity-50 disabled:cursor-wait cursor-pointer whitespace-nowrap"
        >
            <flux:icon.arrow-down-tray wire:loading.remove wire:target="downloadSelected" class="w-4 h-4 shrink-0" />
            <flux:icon.loading wire:loading wire:target="downloadSelected" class="w-4 h-4 animate-spin shrink-0" />
            <span wire:loading.remove wire:target="downloadSelected">Download</span>
            <span wire:loading wire:target="downloadSelected">Preparing...</span>
        </button>
        <flux:modal.trigger name="delete-confirmation">
            <button type="button" class="flex items-center gap-1.5 px-3 py-1.5 max-md:min-h-11 text-sm font-medium text-red-400 hover:bg-white/10 rounded-full transition-colors cursor-pointer whitespace-nowrap">
                <flux:icon.trash class="w-4 h-4 shrink-0" />
                <span>Delete</span>
            </button>
        </flux:modal.trigger>
    </div>

    <div class="pl-1 sm:pl-2">
        <button type="button" wire:click="clearSelection" aria-label="Clear selection" class="p-1.5 max-md:min-h-11 max-md:min-w-11 flex items-center justify-center opacity-75 hover:opacity-100 hover:bg-white/10 rounded-full transition-colors cursor-pointer">
            <flux:icon.x-mark class="w-5 h-5" />
        </button>
    </div>
</div>
