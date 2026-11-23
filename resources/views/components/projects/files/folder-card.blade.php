@props([
    'folder',
    'isSelected' => false,
    'isSearching' => false,
    'folderPath' => null,
])

@php
    $itemsCount = (int) ($folder->files_count ?? 0) + (int) ($folder->children_count ?? 0);
@endphp

<article
    wire:key="folder-{{ $folder->id }}"
    wire:click="navigateToFolder({{ $folder->id }})"
    @class([
        'relative cursor-pointer rounded-2xl border shadow-ambient p-4 sm:p-5 transition-colors group',
        'border-brand ring-1 ring-brand bg-brand-tint/40' => $isSelected,
        'border-ghost bg-surface-card hover:bg-surface-card' => ! $isSelected,
    ])
>
    <label
        x-on:click.stop
        @class([
            'absolute top-1.5 left-1.5 z-20 inline-flex items-center justify-center p-1.5 max-md:min-h-11 max-md:min-w-11 cursor-pointer transition-opacity',
            'opacity-100' => $isSelected,
            'opacity-100 md:opacity-0 md:group-hover:opacity-100 md:focus-within:opacity-100' => ! $isSelected,
        ])
        aria-label="Select {{ $folder->name }}"
    >
        <input
            type="checkbox"
            wire:model.live="selectedIds"
            value="folder_{{ $folder->id }}"
            @checked($isSelected)
            class="peer sr-only"
        />
        <span
            @class([
                'size-5 rounded-md border flex items-center justify-center transition-all duration-200 ease-out shadow-xs',
                'bg-brand-strong border-brand-strong text-white' => $isSelected,
                'bg-surface-card/90 border-ghost text-transparent peer-checked:bg-brand-strong peer-checked:border-brand-strong peer-checked:text-white' => ! $isSelected,
            ])
        >
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </span>
    </label>

    <div class="absolute top-1.5 right-1.5 z-10" x-on:click.stop>
        <flux:dropdown position="bottom" align="end">
            <flux:button
                variant="ghost"
                size="sm"
                icon="ellipsis-vertical"
                aria-label="More options for {{ $folder->name }}"
                class="text-ink-muted hover:text-ink hover:bg-surface-low max-md:min-h-11 max-md:min-w-11 transition-colors"
            />
            <flux:menu>
                <flux:menu.item wire:click="downloadFolder({{ $folder->id }})" icon="arrow-down-tray">Download</flux:menu.item>
                <flux:menu.item
                    x-data="{
                        copy() {
                            const text = '{{ route('project.files', ['project' => $folder->project_id, 'currentFolderId' => $folder->id]) }}';
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(text);
                            } else {
                                const ta = document.createElement('textarea');
                                ta.value = text;
                                document.body.appendChild(ta);
                                ta.select();
                                document.execCommand('copy');
                                ta.remove();
                            }
                            window.Flux && Flux.toast({ heading: 'Link copied', variant: 'success' });
                        }
                    }"
                    x-on:click="copy"
                    icon="link"
                >Copy Link</flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item wire:click="setSingleSelectionAndMove('folder_{{ $folder->id }}')" icon="arrows-right-left">Move</flux:menu.item>
                <flux:menu.item x-on:click="$dispatch('open-rename-folder', { folderId: {{ $folder->id }} })" icon="pencil">Rename</flux:menu.item>
                <flux:menu.item wire:click="setSingleSelectionAndDelete('folder_{{ $folder->id }}')" variant="danger" icon="trash">Delete</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>

    <div class="mb-3.5 text-brand bg-brand-tint w-10 h-10 rounded-xl flex items-center justify-center">
        <flux:icon.folder variant="solid" class="w-5 h-5" />
    </div>

    <div class="font-headline font-semibold text-sm sm:text-base text-ink truncate" title="{{ $folder->name }}">
        {{ $folder->name }}
    </div>

    @if($isSearching && $folderPath)
        <div class="text-xs text-ink-subtle truncate mt-0.5" title="{{ $folderPath }}">{{ $folderPath }}</div>
    @endif

    <div class="text-xs sm:text-sm text-ink-muted mt-1 tabular-nums">
        {{ $itemsCount }} {{ \Illuminate\Support\Str::plural('item', $itemsCount) }}
    </div>
</article>
