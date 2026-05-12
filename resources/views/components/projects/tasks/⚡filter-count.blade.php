<?php

use Livewire\Component;

new class extends Component
{
    public int $count = 0;

    public function mount(int $count): void
    {
        $this->count = $count;
    }
};
?>

<span class="inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-orange-100 text-orange-600 text-xs font-bold">
    {{ $count }}
</span>

