<div>
    <flux:modal name="rename-item-modal" class="md:w-96">
        <form wire:submit="save">
            <flux:heading size="lg">Rename {{ $type === 'file' ? 'File' : 'Folder' }}</flux:heading>
            
            <div class="mt-4">
                <flux:input 
                    wire:model="form.name" 
                    label="Name" 
                    placeholder="Enter new name"
                >
                    @if($type === 'file' && $form->extension)
                        <x-slot name="suffix">
                            {{ $form->extension }}
                        </x-slot>
                    @endif
                </flux:input>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
