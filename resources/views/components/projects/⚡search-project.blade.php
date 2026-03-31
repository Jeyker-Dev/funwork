<?php

use Livewire\Component;

new class extends Component
{
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->dispatch('search-updated', search: $this->search);
    }
};
?>

<div class="-mb-px max-md:hidden w-6/12">
    <flux:input icon="magnifying-glass" placeholder="Search Projects" class="no-ring gray-search" wire:model.live="search"  />
</div>
