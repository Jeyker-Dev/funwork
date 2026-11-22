@props(['item'])

@php
    use Illuminate\Support\Str;
@endphp

<div
    wire:key="inbox-item-{{ $item->id }}"
    x-data="{
        expanded: false,
        truncated: false,
        measure() {
            if (!this.$refs.itemText) {
                return;
            }
            this.truncated = this.$refs.itemText.scrollHeight > this.$refs.itemText.clientHeight + 1;
        }
    }"
    x-init="$nextTick(() => measure())"
    x-on:resize.window.debounce.150ms="measure()"
    class="group flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 sm:gap-4 px-3.5 py-2.5 sm:py-3 rounded-xl hover:bg-surface-low focus-within:bg-surface-low transition-colors duration-150"
>
    <div class="min-w-0 flex-1">
        <p
            x-ref="itemText"
            x-on:click="if (truncated || expanded) { expanded = !expanded }"
            :class="[expanded ? '' : 'line-clamp-2', (truncated || expanded) ? 'cursor-pointer' : '']"
            class="text-sm text-ink break-words leading-relaxed"
        >{{ $item->content }}</p>

        <button
            type="button"
            x-cloak
            x-show="truncated || expanded"
            x-on:click="expanded = !expanded"
            :aria-expanded="expanded.toString()"
            class="mt-1 text-xs font-medium text-accent-content hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded"
            x-text="expanded ? 'Show less' : 'Show more'"
        ></button>
    </div>

    <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0">
        <div class="flex items-center gap-1.5 opacity-100 md:opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100 [@media(hover:none)]:opacity-100 transition-opacity duration-150">
            <button
                type="button"
                wire:click="$dispatch('open-convert-to-task', { item: {{ $item->id }} })"
                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 max-md:min-h-11 rounded-lg bg-surface-card border border-ghost text-ink hover:text-accent-content text-xs font-medium whitespace-nowrap shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
                <flux:icon name="check-circle" class="size-4 text-accent-content shrink-0" aria-hidden="true" />
                <span>Make task</span>
            </button>

            <button
                type="button"
                wire:click="$dispatch('open-convert-to-notebook', { item: {{ $item->id }} })"
                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 max-md:min-h-11 rounded-lg bg-surface-card border border-ghost text-ink hover:text-accent-content text-xs font-medium whitespace-nowrap shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
                <flux:icon name="document-text" class="size-4 text-ink-muted shrink-0" aria-hidden="true" />
                <span>Make note</span>
            </button>

            <button
                type="button"
                wire:click="$dispatch('open-delete-item-modal', { id: {{ $item->id }} })"
                aria-label="Delete item: {{ Str::limit($item->content, 40) }}"
                title="Delete"
                class="inline-flex items-center justify-center p-1.5 max-md:min-h-11 max-md:min-w-11 md:size-7 rounded-lg bg-surface-card border border-ghost text-ink-muted hover:text-ink text-xs shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
                <flux:icon name="trash" class="size-4" aria-hidden="true" />
            </button>
        </div>

        <span class="text-xs text-ink-subtle tabular-nums shrink-0 sm:ml-1">
            {{ $item->created_at->shortAbsoluteDiffForHumans() }}
        </span>
    </div>
</div>
