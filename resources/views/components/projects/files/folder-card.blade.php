@props(['folder', 'isSelected' => false, 'isSearching' => false])

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
    <div class="absolute top-2 right-2 z-10" x-on:click.stop>
        <flux:dropdown position="bottom" align="end">
            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" class="text-gray-400 hover:text-gray-900 hover:bg-white/50 transition-colors" />
            <flux:menu>
                <flux:menu.item wire:click="downloadFolder({{ $folder->id }})" icon="arrow-down-tray">Download</flux:menu.item>
                <flux:menu.item 
                    x-data="{
                        copy() {
                            const text = '{{ route('project.files', ['project' => $folder->project_id, 'currentFolderId' => $folder->id]) }}';
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(text);
                            } else {
                                let ta = document.createElement('textarea');
                                ta.value = text;
                                document.body.appendChild(ta);
                                ta.select();
                                document.execCommand('copy');
                                ta.remove();
                            }
                            window.Flux && Flux.toast({ heading: '¡Enlace copiado!', variant: 'success' });
                        }
                    }" 
                    x-on:click="copy" 
                    icon="link">Copy Link</flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item wire:click="setSingleSelectionAndMove('folder_{{ $folder->id }}')" icon="arrows-right-left">Move</flux:menu.item>
                <flux:menu.item x-on:click="$dispatch('open-rename-folder', { folderId: {{ $folder->id }} })" icon="pencil">Rename</flux:menu.item>
                <flux:menu.item wire:click="setSingleSelectionAndDelete('folder_{{ $folder->id }}')" variant="danger" icon="trash">Delete</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>
    <div class="mb-4 text-orange-600 bg-orange-50 w-10 h-10 rounded-lg flex items-center justify-center">
        <flux:icon.folder variant="solid" class="w-6 h-6" />
    </div>
    <div class="font-semibold text-gray-900 truncate" title="{{ $folder->name }}">{{ $folder->name }}</div>
    
    @if($isSearching && $folder->parent_id)
        @php
            $path = collect($folder->breadcrumbs)->pluck('name')->join(' / ');
        @endphp
        <div class="text-xs text-gray-400 truncate mt-0.5" title="{{ $path }}">{{ $path }}</div>
    @endif
    
    <div class="text-sm text-gray-500 mt-1">{{ $folder->files()->count() }} items</div>
</flux:card>
