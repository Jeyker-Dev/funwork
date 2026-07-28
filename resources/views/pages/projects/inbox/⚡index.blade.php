<?php

use App\Models\InboxItem;
use App\Models\Project;
use App\Livewire\Forms\InboxItemForm;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')]
#[Title('Inbox')]
class extends Component {
    public ?Project $project = null;

    public InboxItemForm $form;

    public function mount(Project $project): void
    {
        $this->project = $project;
        $this->form->projectId = $project->id;
        $this->form->userId = auth()->id();
    }

    public function addItem(): void
    {
        $this->form->store();
    }

    #[On('item-deleted')]
    public function refreshItems(): void
    {
        // Empty method to trigger re-render
    }

    public function with(): array
    {
        return [
            'items' => auth()->user()->inboxItems()
                ->where('project_id', $this->project->id)
                ->latest()
                ->get(),
        ];
    }
};
?>

<div>
    <flux:main container>
        <flux:heading size="xl" level="1">Inbox</flux:heading>
        <flux:subheading size="lg" class="mb-6">Capture ideas, tasks, and notes quickly.</flux:subheading>

        <form wire:submit="addItem" class="mb-8">
            <flux:input wire:model="form.content" placeholder="What's on your mind?" />
            <div class="mt-4 flex justify-end">
                <flux:button type="submit" variant="primary">Add to Inbox</flux:button>
            </div>
        </form>

        <div class="space-y-4">
            @forelse($items as $item)
                <flux:card class="flex justify-between items-center">
                    <div>
                        <p>{{ $item->content }}</p>
                        <p class="text-xs text-zinc-500 mt-1">{{ $item->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="flex space-x-2 gap-2">
                        <flux:button size="sm" variant="ghost" wire:click="$dispatch('open-convert-to-task', { item: {{ $item->id }} })">Task</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="$dispatch('open-convert-to-notebook', { item: {{ $item->id }} })">Note</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="$dispatch('open-delete-item-modal', { id: {{ $item->id }} })">Delete</flux:button>
                    </div>
                </flux:card>
            @empty
                <flux:card>
                    <p class="text-center text-zinc-500">Your inbox is empty. Enjoy the silence.</p>
                </flux:card>
            @endforelse
        </div>
    </flux:main>

    <livewire:projects.inbox.convert-to-task />
    <livewire:projects.inbox.convert-to-notebook />
    <livewire:projects.inbox.delete-item-modal />
</div>
