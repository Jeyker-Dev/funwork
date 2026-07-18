@props(['folder', 'isSelected' => false])

<flux:card wire:key="folder-{{ $folder->id }}" wire:click="navigateToFolder({{ $folder->id }})"
    @class([
        'relative cursor-pointer transition-colors group',
        'border-orange-500 bg-orange-50 ring-1 ring-orange-500' => $isSelected,
        'hover:border-gray-300 bg-white' => !$isSelected,
    ])>
    <div class="absolute top-2 left-2 z-20">
        <input type="checkbox" wire:click.stop wire:model.live="selectedIds" value="folder_{{ $folder->id }}"
            class="rounded border-gray-300 text-orange-600 focus:ring-orange-600 cursor-pointer w-4 h-4
            {{ $isSelected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100' }} transition-opacity">
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
