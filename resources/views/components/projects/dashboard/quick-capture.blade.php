@props(['project'])

<div class="flex flex-col gap-4 rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
    <flux:heading size="lg">Quick Capture</flux:heading>
    <flux:text class="text-sm">Jot down a quick thought or task for your inbox.</flux:text>
    
    <form wire:submit="saveQuickCapture" class="mt-2 flex flex-col gap-3">
        <flux:textarea 
            wire:model="captureForm.content" 
            placeholder="What's on your mind?"
            rows="3"
        />
        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                Capture
            </flux:button>
        </div>
    </form>
</div>
