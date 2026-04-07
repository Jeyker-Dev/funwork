<?php

use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public string $inputClass = '';
    public string $containerClass = '';
    public string $inputPlaceholder = 'Search';

    public function mount(string $inputClass = '', string $containerClass = '', string $inputPlaceholder = ''): void
    {
        $this->inputClass = $inputClass;
        $this->containerClass = $containerClass;
        $this->inputPlaceholder = $inputPlaceholder;
    }

    public function updatedSearch(): void
    {
        $this->dispatch('search-updated', search: $this->search);
    }
};
?>

<div class="{{ $containerClass }}">
    <flux:input icon="magnifying-glass" placeholder="{{ $inputPlaceholder }}" class="no-ring gray-search {{ $inputClass }}" wire:model.live="search"  />
</div>
