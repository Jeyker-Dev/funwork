@props(['isEmpty' => false])

<section aria-label="Capture inbox item" class="w-full bg-surface-card rounded-2xl border border-ghost shadow-ambient p-2 sm:p-2.5 focus-within:border-brand-strong focus-within:ring-4 focus-within:ring-brand/15 transition">
    <form wire:submit="addItem" class="flex items-center gap-2 sm:gap-3">
        <flux:icon name="plus" class="size-5 text-ink-subtle shrink-0 ml-2" aria-hidden="true" />

        <div class="flex-1 min-w-0">
            <label for="inbox-capture-input" class="sr-only">Capture an idea and press Enter</label>
            <input
                id="inbox-capture-input"
                type="text"
                wire:model="form.content"
                x-ref="captureInput"
                placeholder="Capture an idea and press Enter"
                aria-label="Capture an idea and press Enter"
                @if($isEmpty)
                    autofocus
                    x-init="$nextTick(() => $refs.captureInput?.focus())"
                @endif
                x-on:inbox-item-captured.window="$nextTick(() => $refs.captureInput?.focus())"
                x-on:focus-capture-bar.window="$nextTick(() => $refs.captureInput?.focus())"
                class="w-full bg-transparent border-none focus:outline-none focus:ring-0 text-sm sm:text-base px-1 py-2 text-ink placeholder:text-ink-subtle"
            />
        </div>

        <kbd aria-hidden="true" class="hidden sm:inline-flex items-center justify-center px-2 py-1 rounded-md bg-surface-low border border-ghost text-ink-muted text-xs font-medium shrink-0 select-none mr-1">
            ↵ Enter
        </kbd>
    </form>

    @error('form.content')
        <p role="alert" class="px-3 pt-1.5 pb-1 text-xs text-danger dark:text-red-400">{{ $message }}</p>
    @enderror
</section>
