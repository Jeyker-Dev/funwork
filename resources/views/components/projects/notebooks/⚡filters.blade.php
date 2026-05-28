<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div class="flex items-center gap-4">
    <flux:badge rounded class="bg-orange-500! text-white! px-4">All</flux:badge>

    <flux:badge rounded class="bg-orange-500! text-white! px-4">Shared</flux:badge>

    <flux:badge rounded class="bg-orange-500! text-white! px-4">Archived</flux:badge>

    <div class="w-0.5 h-6 bg-gray-300"></div>

    <flux:badge rounded icon="bars-3-bottom-left" class="bg-transparent! text-black px-4">Sort: Recent</flux:badge>
</div>
