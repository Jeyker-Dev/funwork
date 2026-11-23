@props([
    'breadcrumbs' => [],
])

@php
    $maxVisibleBreadcrumbs = 3;
    $crumbs = collect($breadcrumbs);
    $currentCrumb = $crumbs->last();
    $ancestorCrumbs = $crumbs->slice(0, -1);
    $parentCrumb = $ancestorCrumbs->last();
@endphp

<nav
    data-files-breadcrumbs
    aria-label="{{ __('Folder navigation') }}"
    class="flex flex-wrap items-center gap-1.5 text-sm text-ink-muted font-medium"
>
    <button
        type="button"
        wire:click="navigateToRoot"
        @class([
            'hover:text-ink transition-colors cursor-pointer rounded-lg px-1.5 py-0.5 max-md:min-h-11 inline-flex items-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
            'text-ink font-semibold' => empty($breadcrumbs),
        ])
    >
        {{ __('Files') }}
    </button>

    @if(!empty($breadcrumbs))
        <flux:icon.chevron-right class="w-4 h-4 text-ink-subtle shrink-0" aria-hidden="true" />

        @if($crumbs->count() > $maxVisibleBreadcrumbs)
            <button
                type="button"
                wire:click="navigateToFolder({{ $crumbs->first()->id }})"
                class="hover:text-ink transition-colors cursor-pointer rounded-lg px-1.5 py-0.5 max-md:min-h-11 inline-flex items-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
                {{ $crumbs->first()->name }}
            </button>
            <flux:icon.chevron-right class="w-4 h-4 text-ink-subtle shrink-0" aria-hidden="true" />
            <span class="text-ink-subtle px-1">...</span>
            <flux:icon.chevron-right class="w-4 h-4 text-ink-subtle shrink-0" aria-hidden="true" />
            <button
                type="button"
                wire:click="navigateToFolder({{ $parentCrumb->id }})"
                class="hover:text-ink transition-colors cursor-pointer rounded-lg px-1.5 py-0.5 max-md:min-h-11 inline-flex items-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
                {{ $parentCrumb->name }}
            </button>
            <flux:icon.chevron-right class="w-4 h-4 text-ink-subtle shrink-0" aria-hidden="true" />
            <span class="text-ink font-semibold px-1.5 py-0.5">{{ $currentCrumb->name }}</span>
        @else
            @foreach($ancestorCrumbs as $crumb)
                <button
                    type="button"
                    wire:click="navigateToFolder({{ $crumb->id }})"
                    class="hover:text-ink transition-colors cursor-pointer rounded-lg px-1.5 py-0.5 max-md:min-h-11 inline-flex items-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    {{ $crumb->name }}
                </button>
                <flux:icon.chevron-right class="w-4 h-4 text-ink-subtle shrink-0" aria-hidden="true" />
            @endforeach
            <span class="text-ink font-semibold px-1.5 py-0.5">{{ $currentCrumb->name }}</span>
        @endif
    @endif
</nav>
