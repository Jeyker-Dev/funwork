@props(['file', 'isSelected' => false, 'isSearching' => false])

@php
    use App\Enums\FileCategory;

    $category = $file->category;
@endphp

<flux:card wire:key="file-{{ $file->id }}"
    @class([
        'relative cursor-pointer transition-colors p-0 overflow-hidden flex flex-col group',
        'border-brand-strong ring-1 ring-brand-strong bg-brand-tint' => $isSelected,
        'hover:border-ghost bg-surface-card' => !$isSelected,
    ])
    x-on:click.prevent="$dispatch('open-lightbox', { id: {{ $file->id }} })">
    <div class="absolute top-2 left-2 z-30">
        <input type="checkbox" wire:model.live="selectedIds" value="file_{{ $file->id }}" x-on:click.stop
            class="rounded border-ghost text-brand-strong focus:ring-brand-strong cursor-pointer w-4 h-4
            {{ $isSelected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100' }} transition-opacity bg-surface-card/80 backdrop-blur-sm">
    </div>
    <div class="absolute top-2 right-2 z-20" x-on:click.stop>
        <flux:dropdown position="bottom" align="end">
            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" class="text-ink-muted hover:text-ink bg-surface-card/80 backdrop-blur-sm shadow-sm hover:bg-surface-card transition-colors" />
            <flux:menu>
                <flux:menu.item wire:click="downloadSingleFile({{ $file->id }})" icon="arrow-down-tray">Download</flux:menu.item>
                <flux:menu.item 
                    x-data="{
                        copy() {
                            const text = '{{ route('project.files', ['project' => $file->project_id, 'currentFolderId' => $file->folder_id]) }}';
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
                <flux:menu.item wire:click="setSingleSelectionAndMove('file_{{ $file->id }}')" icon="arrows-right-left">Move</flux:menu.item>
                <flux:menu.item x-on:click="$dispatch('open-rename-file', { fileId: {{ $file->id }} })" icon="pencil">Rename</flux:menu.item>
                <flux:menu.item wire:click="setSingleSelectionAndDelete('file_{{ $file->id }}')" variant="danger" icon="trash">Delete</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>

    <!-- File Preview Area -->
    <div class="h-32 border-b border-ghost flex items-center justify-center relative overflow-hidden {{ $isSelected ? 'bg-brand-tint' : 'bg-surface-low' }}">
        @if($category === FileCategory::Image)
            <div class="absolute inset-0 bg-gradient-to-tr from-brand-tint/60 to-surface-card z-0"></div>
        @endif
        <div class="relative z-10 {{ $category->previewClasses() }}">
            <flux:icon :icon="$category->icon()" class="w-10 h-10" />
        </div>
    </div>

    <!-- File Metadata -->
    <div class="p-4 flex gap-3 items-start relative z-10 transition-colors {{ $isSelected ? 'bg-brand-tint' : 'bg-surface-card' }}">
        <div class="mt-1 {{ $category->iconColor() }}">
            <flux:icon :icon="$category->icon()" class="w-5 h-5" />
        </div>
        <div class="min-w-0 flex-1">
            <div class="font-semibold text-ink truncate" title="{{ $file->name }}">{{ $file->name }}</div>
            @if($isSearching && $file->folder_path)
                <div class="text-xs text-ink-subtle truncate mt-0.5" title="{{ $file->folder_path }}">{{ $file->folder_path }}</div>
            @endif
            <div class="text-xs text-ink-muted mt-1 uppercase tracking-wide">
                {{ $file->extension_label }} • {{ $file->size_in_megabytes }} MB
            </div>
        </div>
    </div>
</flux:card>
