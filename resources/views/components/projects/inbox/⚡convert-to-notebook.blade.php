<?php

use App\Models\InboxItem;
use App\Livewire\Forms\ConvertInboxToNotebookForm;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ConvertInboxToNotebookForm $form;

    public bool $redirectAfterConvert = true;

    #[On('open-convert-to-notebook')]
    public function loadModal($item = null): void
    {
        $this->redirectAfterConvert = true;
        $this->prepareModal($item);
    }

    #[On('open-convert-to-notebook-for-process')]
    public function loadModalForProcess($item = null): void
    {
        $this->redirectAfterConvert = false;
        $this->prepareModal($item);
    }

    public function convert(): void
    {
        $convertedItemId = $this->form->item?->id;
        $this->form->store();

        Flux::modal('convert-to-notebook')->close();

        if ($this->redirectAfterConvert) {
            $this->redirect(route('project.notebooks', $this->form->projectId));

            return;
        }

        Flux::toast(
            heading: 'Note Created',
            text: 'The inbox item has been converted to a notebook.',
            variant: 'success',
            duration: 3000,
        );

        $this->dispatch('item-converted', id: $convertedItemId);
    }

    private function prepareModal($item = null): void
    {
        if (! $item) {
            return;
        }

        $inboxItem = InboxItem::where('user_id', auth()->id())->find($item);
        abort_unless($inboxItem, 404);

        $this->authorize('update', $inboxItem);

        $this->form->setItem($inboxItem);

        Flux::modal('convert-to-notebook')->show();
    }

    public function with(): array
    {
        return [
            'projects' => auth()->user()->projects()->get(),
        ];
    }
};
?>

<flux:modal name="convert-to-notebook" class="w-xl">
    <form wire:submit="convert">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Convert to Note</flux:heading>
                <flux:text class="mt-2">
                    Create a new notebook from this inbox item.
                </flux:text>
            </div>

            <flux:select variant="listbox" label="Project" placeholder="Choose project..." wire:model.live="form.projectId">
                @foreach($projects as $project)
                    <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if($selectedProject = $projects->firstWhere('id', $form->projectId))
                <flux:select variant="listbox" label="Area" placeholder="No area" wire:model="form.areaId">
                    <flux:select.option :value="null">No area</flux:select.option>
                    @foreach($selectedProject->areas as $area)
                        <flux:select.option :value="$area->id">{{ $area->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input label="Name" wire:model="form.name" />

            <flux:textarea label="Description" wire:model="form.description" />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Convert to Note</flux:button>
            </div>
        </div>
    </form>
</flux:modal>