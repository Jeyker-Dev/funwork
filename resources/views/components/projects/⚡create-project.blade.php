<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div class="flex flex-col items-center justify-center gap-4 border-2 border-dashed border-gray-300 rounded-lg p-6 mt-6 max-w-96 cursor-pointer">
   <div class="flex flex-col items-center justify-center bg-gray-300 rounded-full w-12 h-12 ">
       <flux:icon.plus variant="solid" class="text-black"/>
   </div>

    <p class="text-black font-bold text-lg">
        Create your first project
    </p>

    <p class="text-gray-500 font-normal text-center w-8/12">
        Start your first workspace and organize your tasks effortessly.
    </p>
</div>
