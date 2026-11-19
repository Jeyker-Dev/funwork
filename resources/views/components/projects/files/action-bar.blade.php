@props([
    'breadcrumbs' => [],
    'hasItems' => false,
])

@php
    $maxVisibleBreadcrumbs = 3;
    $crumbs = collect($breadcrumbs);
    $currentCrumb = $crumbs->last();
    $ancestorCrumbs = $crumbs->slice(0, -1);
    $parentCrumb = $ancestorCrumbs->last();
@endphp

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <!-- Breadcrumbs -->
    <div class="flex flex-wrap items-center gap-2 text-sm text-ink-muted font-medium">
        <button wire:click="navigateToRoot" class="hover:text-ink transition-colors max-md:min-h-11 {{ empty($breadcrumbs) ? 'text-ink font-semibold' : '' }}">Files</button>

        @if(!empty($breadcrumbs))
            <flux:icon.chevron-right class="w-4 h-4 text-ink-subtle" />

            @if($crumbs->count() > $maxVisibleBreadcrumbs)
                <button wire:click="navigateToFolder({{ $crumbs->first()->id }})" class="hover:text-ink transition-colors">{{ $crumbs->first()->name }}</button>
                <flux:icon.chevron-right class="w-4 h-4 text-ink-subtle" />
                <span class="text-ink-subtle">...</span>
                <flux:icon.chevron-right class="w-4 h-4 text-ink-subtle" />
                <button wire:click="navigateToFolder({{ $parentCrumb->id }})" class="hover:text-ink transition-colors">{{ $parentCrumb->name }}</button>
                <flux:icon.chevron-right class="w-4 h-4 text-ink-subtle" />
                <span class="text-ink font-semibold">{{ $currentCrumb->name }}</span>
            @else
                @foreach($ancestorCrumbs as $crumb)
                    <button wire:click="navigateToFolder({{ $crumb->id }})" class="hover:text-ink transition-colors">{{ $crumb->name }}</button>
                    <flux:icon.chevron-right class="w-4 h-4 text-ink-subtle" />
                @endforeach
                <span class="text-ink font-semibold">{{ $currentCrumb->name }}</span>
            @endif
        @endif
    </div>

    <!-- Actions -->
    @if($hasItems)
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <div class="w-full sm:w-64 min-w-0">
                <flux:input wire:model.live.debounce.300ms="searchQuery" icon="magnifying-glass" placeholder="Search files..." />
            </div>
            <div class="flex gap-1 bg-surface-card rounded-lg shadow-sm p-1 border border-ghost">
                <button type="button" aria-label="Grid view" class="p-1.5 rounded-md hover:bg-surface-low text-ink max-md:min-h-10 max-md:min-w-10 flex items-center justify-center">
                    <flux:icon.squares-2x2 variant="solid" class="w-5 h-5" />
                </button>
                <button type="button" aria-label="List view" class="p-1.5 rounded-md hover:bg-surface-low text-ink-muted max-md:min-h-10 max-md:min-w-10 flex items-center justify-center">
                    <flux:icon.list-bullet class="w-5 h-5" />
                </button>
            </div>
            <flux:modal.trigger name="create-folder">
                <flux:button icon="folder-plus" variant="outline" class="max-md:min-h-11">New Folder</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="upload-file">
                <flux:button icon="document-plus" variant="primary" class="max-md:min-h-11">New File</flux:button>
            </flux:modal.trigger>
        </div>
    @endif
</div>
