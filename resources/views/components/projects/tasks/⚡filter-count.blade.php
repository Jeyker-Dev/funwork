<?php

use Livewire\Component;

new class extends Component
{
    public int $count = 0;
    public bool $active = false;

    public function mount(int $count, bool $active = false): void
    {
        $this->count = $count;
        $this->active = $active;
    }
};
?>

<span class="inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full text-xs font-bold {{ $active ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-500' }}">
    {{ $count }}
</span>
