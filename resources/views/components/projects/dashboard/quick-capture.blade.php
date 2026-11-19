@props(['project'])

<div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-2 pl-4">
    <form wire:submit="saveQuickCapture" class="flex items-center gap-2">
        <div class="flex-1 min-w-0">
            <input 
                type="text" 
                wire:model="captureForm.content"
                placeholder="What's on your mind? (Quick Capture)"
                aria-label="Quick capture"
                class="w-full bg-transparent border-none focus:ring-0 text-sm px-2 py-2 text-ink placeholder:text-ink-subtle"
                required
            />
        </div>
        <flux:button type="submit" variant="primary" class="max-md:min-h-11 shrink-0">
            Capture
        </flux:button>
    </form>
</div>
