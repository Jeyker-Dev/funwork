@props([
    'targetName',
    'maxSizeLabel',
])

<div
    x-show="isDragging"
    x-cloak
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    role="dialog"
    aria-label="{{ __('Drop files to upload') }}"
    class="fixed inset-0 z-50 bg-surface/85 dark:bg-zinc-950/85 backdrop-blur-md flex items-center justify-center p-6 pointer-events-none"
>
    <div class="max-w-lg w-full bg-surface-card rounded-2xl border-2 border-dashed border-brand shadow-ambient p-8 sm:p-10 text-center space-y-4">
        <div class="size-14 rounded-2xl bg-brand-tint text-brand flex items-center justify-center mx-auto">
            <flux:icon.arrow-up-tray class="size-7" />
        </div>

        <div class="space-y-1.5">
            <h2 class="font-headline font-bold text-xl text-ink">
                Drop to upload to {{ $targetName }}
            </h2>
            <p class="text-sm text-ink-muted">
                Up to {{ $maxSizeLabel }} per file
            </p>
        </div>

        <p class="text-xs text-ink-subtle">
            {{ __('Press Esc or drag outside the window to cancel') }}
        </p>
    </div>
</div>

<div
    x-show="isUploading"
    x-cloak
    class="fixed bottom-6 right-6 z-50 bg-surface-card border border-ghost shadow-ambient rounded-xl px-4 py-3 flex items-center gap-3 text-sm font-medium text-ink"
>
    <flux:icon.loading class="size-4 animate-spin text-brand" />
    <span>{{ __('Uploading files...') }}</span>
</div>
