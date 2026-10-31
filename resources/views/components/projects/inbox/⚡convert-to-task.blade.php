<?php

use App\Models\InboxItem;
use App\Livewire\Forms\ConvertInboxToTaskForm;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ConvertInboxToTaskForm $form;

    #[On('open-convert-to-task')]
    public function loadModal($item = null): void
    {
        if ($item) {
            $inboxItem = InboxItem::findOrFail($item);

            $this->authorize('update', $inboxItem);

            $this->form->setItem($inboxItem);

            Flux::modal('convert-to-task')->show();
        }
    }

    public function convert(): void
    {
        $this->form->store();

        Flux::modal('convert-to-task')->close();
        
        $this->redirect(route('project.tasks', $this->form->projectId));
    }

    public function with(): array
    {
        return [
            'projects' => auth()->user()->projects()->get(),
        ];
    }
};
?>

<flux:modal name="convert-to-task" class="w-xl">
    <form wire:submit="convert">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Convert to Task</flux:heading>
                <flux:text class="mt-2">
                    Create a new task from this inbox item.
                </flux:text>
            </div>

            <flux:select variant="listbox" label="Project" placeholder="Choose project..." wire:model="form.projectId">
                @foreach($projects as $project)
                    <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input label="Title" wire:model="form.title" />

            <flux:textarea label="Description" wire:model="form.description" />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Convert to Task</flux:button>
            </div>
        </div>
    </form>
</flux:modal>