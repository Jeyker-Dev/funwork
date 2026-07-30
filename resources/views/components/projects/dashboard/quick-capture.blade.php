@props(['project'])

<div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm p-2 pl-4">
    <form wire:submit="saveQuickCapture" class="flex items-center gap-2">
        <div class="flex-1">
            <input 
                type="text" 
                wire:model="captureForm.content"
                placeholder="What's on your mind? (Quick Capture)"
                class="w-full bg-transparent border-none focus:ring-0 text-sm px-2 py-2 text-neutral-900 dark:text-neutral-100 placeholder-neutral-500"
                required
            />
        </div>
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white rounded-xl px-4 py-2 text-sm font-medium transition-colors flex items-center justify-center whitespace-nowrap">
            Capture
        </button>
    </form>
</div>
