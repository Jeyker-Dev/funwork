@props(['project'])

<section aria-label="Quick capture" class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-2 sm:p-2.5 pl-4 focus-within:border-brand-strong focus-within:ring-4 focus-within:ring-brand/15 transition">
    <form wire:submit="saveQuickCapture" class="flex items-center gap-2 sm:gap-3">
        <flux:icon name="pencil-square" class="size-5 text-accent-content shrink-0 hidden sm:block" />

        <div class="flex-1 min-w-0">
            <label for="quick-capture-input" class="sr-only">Capture an idea for the inbox</label>
            <input
                id="quick-capture-input"
                type="text"
                wire:model="captureForm.content"
                placeholder="Capture an idea for the inbox"
                class="w-full bg-transparent border-none focus:outline-none focus:ring-0 text-sm sm:text-base px-1 sm:px-2 py-2 text-ink placeholder:text-ink-subtle"
                required
            />
        </div>

        <flux:button type="submit" variant="primary" class="min-h-11 shrink-0">
            Capture
        </flux:button>
    </form>

    @error('captureForm.content')
        <p class="px-2 pb-1 text-xs text-danger dark:text-red-400">{{ $message }}</p>
    @enderror
</section>
