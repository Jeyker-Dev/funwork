<?php

use Livewire\Component;
use App\Models\Notebook;
use Livewire\Attributes\On;
use App\Models\Project;
use Livewire\WithPagination;

new class extends Component
{
    public ?Project $project = null;

    use WithPagination;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }
    public function with(): array
    {
        return [
            'notebooks' => $this->project->notebooks()->paginate(6),
        ];
    }

    #[On('created-notebook')]
    public function refreshNotebooks(): void
    {
        $this->with();
    }
};
?>

<div>
    <div class="grid grid-cols-3 gap-4 mb-6">
        @foreach($notebooks as $notebook)
            <flux:card class="space-y-6">

                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ $notebook->name }}</flux:heading>

                    <flux:icon.ellipsis-vertical class="size-7" />
                </div>

                <div>
                    <flux:text class="mt-2">{{ $notebook->description }}</flux:text>
                </div>

                <flux:separator />

                <div class="flex items-center justify-between">
                    <flux:text class="mt-2 text-xs!">Modified at 2h</flux:text>

                    <flux:avatar src="https://unavatar.io/x/calebporzio" />
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:pagination :paginator="$notebooks" />
</div>
