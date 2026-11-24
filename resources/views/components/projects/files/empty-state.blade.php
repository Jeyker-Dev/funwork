@props([
    'inFolder' => false,
])

<div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient text-center px-6 py-16 space-y-4">
    <div class="size-12 rounded-2xl bg-surface-low flex items-center justify-center mx-auto text-ink-subtle">
        <flux:icon.folder-open class="size-6" />
    </div>

    <div class="space-y-1.5 max-w-md mx-auto">
        <h2 class="font-headline font-bold text-lg text-ink">
            {{ $inFolder ? __('This folder is empty') : __('No files yet') }}
        </h2>
        <p class="text-sm text-ink-muted">
            {{ $inFolder
                ? __('Upload files here or create a subfolder to keep things organized.')
                : __('Store project assets, PDFs, and documents in one place or organize them into folders.') }}
        </p>
    </div>

    <div class="pt-2 flex flex-col items-center gap-3">
        <div class="flex flex-wrap items-center justify-center gap-3">
            <flux:modal.trigger name="upload-file">
                <flux:button variant="primary" icon="arrow-up-tray" class="max-md:min-h-11">
                    {{ __('Upload') }}
                </flux:button>
            </flux:modal.trigger>

            <flux:modal.trigger name="create-folder">
                <flux:button variant="outline" icon="folder-plus" class="bg-surface-card! border-ghost! max-md:min-h-11">
                    {{ __('New folder') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        <p class="text-xs text-ink-subtle">
            {{ __('or drag files here') }}
        </p>
    </div>
</div>
