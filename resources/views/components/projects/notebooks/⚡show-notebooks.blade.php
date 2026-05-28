<?php

use Livewire\Component;
use App\Models\Notebook;
use Livewire\Attributes\On;

new class extends Component
{
    public function with(): array
    {
        return [
            'notebooks' => Notebook::all(),
        ];
    }

    #[On('created-notebook')]
    public function refreshNotebooks(): void
    {
        $this->with();
    }
};
?>

<div class="grid grid-cols-3 gap-4">
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
