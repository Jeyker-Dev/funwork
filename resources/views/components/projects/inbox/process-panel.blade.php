@props([
    'item',
    'position',
    'total',
])

@php
    $progressPercent = $total > 0 ? (int) round(($position / $total) * 100) : 0;
@endphp

<div
    x-data="{
        isTypingTarget(target) {
            if (!target) {
                return false;
            }

            return ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable;
        },
        hasOpenDialog() {
            return document.querySelector('dialog[open]') !== null;
        },
        handleKeydown(event) {
            if (this.hasOpenDialog() || this.isTypingTarget(event.target)) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                $wire.closeProcessMode();
                return;
            }

            if (event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            const key = event.key.toLowerCase();

            if (key === 't') {
                event.preventDefault();
                $dispatch('open-convert-to-task-for-process', { item: {{ $item->id }} });
            } else if (key === 'n') {
                event.preventDefault();
                $dispatch('open-convert-to-notebook-for-process', { item: {{ $item->id }} });
            } else if (key === 'd') {
                event.preventDefault();
                $dispatch('open-delete-item-modal', { id: {{ $item->id }} });
            }
        }
    }"
    x-on:keydown.window="handleKeydown($event)"
    role="dialog"
    aria-modal="true"
    aria-labelledby="process-panel-item-text"
    class="fixed inset-0 lg:left-64 z-30 m-0! overscroll-contain flex items-center justify-center bg-zinc-950/35 dark:bg-black/60 backdrop-blur-sm p-3 sm:p-6"
>
    <div class="relative w-full max-sm:min-h-[calc(100dvh-1.5rem)] max-sm:justify-between sm:max-w-xl bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 sm:p-10 flex flex-col">
        <div class="space-y-3">
            <div class="flex items-center justify-between gap-4">
                <span class="text-xs font-medium text-ink-muted tabular-nums">
                    {{ $position }} of {{ $total }}
                </span>

                <button
                    type="button"
                    wire:click="closeProcessMode"
                    aria-label="Close process mode"
                    class="inline-flex items-center justify-center p-1.5 max-md:min-h-11 max-md:min-w-11 md:size-8 rounded-lg text-ink-subtle hover:text-ink hover:bg-surface-low transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    <flux:icon name="x-mark" class="size-5" aria-hidden="true" />
                </button>
            </div>

            <div
                class="w-full h-1.5 bg-surface-highest rounded-full overflow-hidden"
                role="progressbar"
                aria-valuenow="{{ $position }}"
                aria-valuemin="1"
                aria-valuemax="{{ $total }}"
                aria-label="Inbox processing progress"
            >
                <div
                    class="h-full bg-gradient-to-r from-brand to-brand-warm rounded-full transition-all duration-200 ease-out"
                    style="width: {{ $progressPercent }}%"
                ></div>
            </div>
        </div>

        <div class="my-8 sm:my-7">
            <h2 id="process-panel-item-text" class="font-headline font-semibold text-xl sm:text-2xl text-ink tracking-tight leading-snug break-words">
                {{ $item->content }}
            </h2>
            <p class="font-body text-sm text-ink-muted mt-2.5">
                Captured {{ $item->created_at->diffForHumans() }}
            </p>
        </div>

        <div class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full">
                <button
                    type="button"
                    wire:click="$dispatch('open-convert-to-task-for-process', { item: {{ $item->id }} })"
                    class="inline-flex items-center justify-center gap-2 py-3 px-4 max-md:min-h-11 rounded-lg bg-gradient-to-r from-brand to-brand-warm hover:from-brand-hover hover:to-brand-hover-warm text-brand-ink font-semibold text-sm shadow-xs transition-all focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/25"
                >
                    <span>Make task</span>
                    <kbd aria-hidden="true" class="hidden sm:inline-flex items-center justify-center bg-white/30 text-brand-ink text-xs font-mono px-1.5 py-0.5 rounded">T</kbd>
                </button>

                <button
                    type="button"
                    wire:click="$dispatch('open-convert-to-notebook-for-process', { item: {{ $item->id }} })"
                    class="inline-flex items-center justify-center gap-2 py-3 px-4 max-md:min-h-11 rounded-lg bg-surface-card border border-ghost text-ink hover:bg-surface-low font-medium text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    <span>Make note</span>
                    <kbd aria-hidden="true" class="hidden sm:inline-flex items-center justify-center bg-surface-low border border-ghost text-ink-muted text-xs font-mono px-1.5 py-0.5 rounded">N</kbd>
                </button>

                <button
                    type="button"
                    wire:click="$dispatch('open-delete-item-modal', { id: {{ $item->id }} })"
                    class="inline-flex items-center justify-center gap-2 py-3 px-4 max-md:min-h-11 rounded-lg bg-surface-card border border-ghost text-ink-muted hover:text-ink hover:bg-surface-low font-medium text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    <span>Delete</span>
                    <kbd aria-hidden="true" class="hidden sm:inline-flex items-center justify-center bg-surface-low border border-ghost text-ink-muted text-xs font-mono px-1.5 py-0.5 rounded">D</kbd>
                </button>
            </div>

            <div class="text-center">
                <button
                    type="button"
                    wire:click="skipCurrentItem"
                    class="inline-flex items-center justify-center text-sm text-ink-muted hover:text-ink font-medium max-md:min-h-11 px-4 py-1.5 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    Skip
                </button>
            </div>
        </div>
    </div>
</div>
