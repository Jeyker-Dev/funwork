<?php

use App\Models\InboxItem;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ?InboxItem $item = null;

    #[On('open-delete-item-modal')]
    public function loadModal($id = null): void
    {
        if ($id) {
            $this->item = InboxItem::where('user_id', auth()->id())->find($id);
            abort_unless($this->item, 404);

            $this->authorize('delete', $this->item);

            Flux::modal('delete-item-modal')->show();
        }
    }

    public function delete(): void
    {
        if ($this->item) {
            $this->authorize('delete', $this->item);

            $this->item->delete();
            $this->item = null;

            Flux::toast(
                heading: 'Item Deleted',
                text: 'The idea has been removed from your inbox.',
                variant: 'success',
                duration: 3000,
            );
        }

        Flux::modal('delete-item-modal')->close();

        $this->dispatch('item-deleted');
    }
};
?>

<flux:modal name="delete-item-modal" class="min-w-[22rem]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Delete Idea</flux:heading>
            <flux:text class="mt-2">
                Are you sure you want to delete this idea? This action cannot be undone.
            </flux:text>
        </div>

        <div class="flex justify-end space-x-2 gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="delete">Delete</flux:button>
        </div>
    </div>
</flux:modal>
