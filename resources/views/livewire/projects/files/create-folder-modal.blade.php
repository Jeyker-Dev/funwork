<flux:modal name="create-folder" class="min-w-[22rem]">
    <form wire:submit="save">
        <flux:heading size="lg" class="mb-4">Create New Folder</flux:heading>

        <flux:input wire:model="form.name" label="Folder Name" placeholder="e.g. Assets" class="mb-2" />
        <flux:error name="form.name" class="mb-4" />

        <div class="flex gap-2 justify-end mt-4">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">Create Folder</flux:button>
        </div>
    </form>
</flux:modal>
