@props([
    'file',
    'isSelected' => false,
    'showFolderPath' => false,
    'folderPath' => null,
])

@php
    use App\Enums\FileCategory;

    $category = $file->category;
    $previewUrl = route('project.files.preview', ['project' => $file->project_id, 'projectFile' => $file->id]);
@endphp

<article
    wire:key="file-{{ $file->id }}"
    x-on:click.prevent="$dispatch('open-lightbox', { id: {{ $file->id }} })"
    @class([
        'relative cursor-pointer rounded-2xl border shadow-ambient overflow-hidden flex flex-col transition-colors group',
        'border-brand ring-1 ring-brand bg-brand-tint/40' => $isSelected,
        'border-ghost bg-surface-card hover:bg-surface-card' => ! $isSelected,
    ])
>
    <label
        x-on:click.stop
        @class([
            'absolute top-1.5 left-1.5 z-30 inline-flex items-center justify-center p-1.5 max-md:min-h-11 max-md:min-w-11 cursor-pointer transition-opacity',
            'opacity-100' => $isSelected,
            'opacity-100 md:opacity-0 md:group-hover:opacity-100 md:focus-within:opacity-100' => ! $isSelected,
        ])
        aria-label="Select {{ $file->name }}"
    >
        <input
            type="checkbox"
            wire:model.live="selectedIds"
            value="file_{{ $file->id }}"
            @checked($isSelected)
            class="peer sr-only"
        />
        <span
            @class([
                'size-5 rounded-md border flex items-center justify-center transition-all duration-200 ease-out shadow-xs',
                'bg-brand-strong border-brand-strong text-white' => $isSelected,
                'bg-surface-card/90 backdrop-blur-sm border-ghost text-transparent peer-checked:bg-brand-strong peer-checked:border-brand-strong peer-checked:text-white' => ! $isSelected,
            ])
        >
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </span>
    </label>

    <div class="absolute top-1.5 right-1.5 z-20" x-on:click.stop>
        <flux:dropdown position="bottom" align="end">
            <flux:button
                variant="ghost"
                size="sm"
                icon="ellipsis-vertical"
                aria-label="More options for {{ $file->name }}"
                class="text-ink-muted hover:text-ink bg-surface-card/85 backdrop-blur-sm shadow-xs hover:bg-surface-card max-md:min-h-11 max-md:min-w-11 transition-colors"
            />
            <flux:menu>
                <flux:menu.item wire:click="downloadSingleFile({{ $file->id }})" icon="arrow-down-tray">Download</flux:menu.item>
                <flux:menu.item
                    x-data="{
                        copy() {
                            const text = '{{ route('project.files', ['project' => $file->project_id, 'currentFolderId' => $file->folder_id]) }}';
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
                <flux:menu.item wire:click="setSingleSelectionAndMove('file_{{ $file->id }}')" icon="arrows-right-left">Move</flux:menu.item>
                <flux:menu.item x-on:click="$dispatch('open-rename-file', { fileId: {{ $file->id }} })" icon="pencil">Rename</flux:menu.item>
                <flux:menu.item wire:click="setSingleSelectionAndDelete('file_{{ $file->id }}')" variant="danger" icon="trash">Delete</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>

    <div class="h-28 sm:h-32 border-b border-ghost flex items-center justify-center relative overflow-hidden bg-surface-low">
        @if($category === FileCategory::Image)
            <div class="absolute inset-0 flex items-center justify-center text-ink-subtle" aria-hidden="true">
                <flux:icon.photo class="w-8 h-8" />
            </div>
            <img
                src="{{ $previewUrl }}"
                alt="{{ $file->name }}"
                loading="lazy"
                onerror="this.style.display='none'"
                class="relative z-10 w-full h-full object-cover"
            />
        @else
            <div class="relative z-10 {{ $category->previewClasses() }}">
                <flux:icon :icon="$category->icon()" class="w-9 h-9 sm:w-10 sm:h-10" />
            </div>
        @endif
    </div>

    <div @class([
        'p-3 sm:p-4 flex gap-2.5 sm:gap-3 items-start relative z-10 transition-colors flex-1',
        'bg-brand-tint/40' => $isSelected,
        'bg-surface-card' => ! $isSelected,
    ])>
        <div class="mt-0.5 shrink-0 {{ $category->iconColor() }}" aria-hidden="true">
            <flux:icon :icon="$category->icon()" class="w-4 h-4 sm:w-5 sm:h-5" />
        </div>
        <div class="min-w-0 flex-1">
            <div class="font-headline font-semibold text-sm text-ink truncate" title="{{ $file->name }}">
                {{ $file->name }}
            </div>
            @if($showFolderPath && $folderPath)
                <div class="text-xs text-ink-subtle truncate mt-0.5" title="{{ $folderPath }}">
                    {{ $folderPath }}
                </div>
            @endif
            <div class="text-xs text-ink-muted mt-1 truncate">
                {{ $file->type_label }} · {{ $file->formatted_size }}
            </div>
        </div>
    </div>
</article>
