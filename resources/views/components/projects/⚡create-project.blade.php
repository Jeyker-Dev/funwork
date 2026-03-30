<?php

use Livewire\Component;

new class extends Component {
    public function save()
    {
        dd('save');
    }
};
?>

<div>
    <flux:modal.trigger name="create-project">
        <div
            class="flex flex-col items-center justify-center gap-4 border-2 border-dashed border-gray-300 rounded-lg p-6 mt-6 max-w-96 cursor-pointer">
            <div class="flex flex-col items-center justify-center bg-gray-300 rounded-full w-10 h-10 md:w-12 md:h-12">
                <flux:icon.plus variant="solid" class="text-black size-6 md:size-7"/>
            </div>

            <p class="text-black font-bold md:text-lg">
                Create your first project
            </p>

            <p class="text-gray-500 font-normal text-center w-8/12 text-sm md:text-base">
                Start your first workspace and organize your tasks effortlessly.
            </p>
        </div>
    </flux:modal.trigger>

    <flux:modal name="create-project" class="w-72 md:w-full">
        <form action="">
            <div class="space-y-6">
                <div>
                    <flux:heading class="text-lg md:text-xl">
                        Create Project
                    </flux:heading>

                    <flux:text class="text-xs md:text-sm mt-2">
                        Define the parameters of your new editorial or technical workspace.
                    </flux:text>
                </div>

                <div class="flex flex-col md:flex-row md:items-center justify-between w-full gap-4 md:gap-0">
                    <div class="md:w-6/12">
                        <flux:input label="Project Name" placeholder="Example: Q3 Brand Expansion"/>
                    </div>

                    <div class="">
                        <flux:radio.group label="Icon" wire:model="role" variant="segmented"
                                          class="bg-linear-to-r from-orange-500 to-yellow-500">
                            <flux:radio icon="wrench"/>
                            <flux:radio icon="pencil-square"/>
                            <flux:radio icon="eye"/>
                        </flux:radio.group>
                    </div>
                </div>

                <div>
                    <flux:textarea label="Description"
                                   placeholder="Describe the objectives and key results for this project..."/>
                </div>

                <div class="flex flex-col md:flex-row md:items-center justify-between w-full gap-4 md:gap-0">
                    <div class="md:w-5/12">
                        <flux:select variant="listbox" placeholder="Choose type..." label="Project type">
                            <flux:select.option>Programming</flux:select.option>
                            <flux:select.option>Study</flux:select.option>
                            <flux:select.option>Music</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="md:w-5/12">
                        <flux:select variant="listbox" placeholder="Choose status..." label="Initial status">
                            <flux:select.option>Planning</flux:select.option>
                            <flux:select.option>Active</flux:select.option>
                            <flux:select.option>On Hold</flux:select.option>
                        </flux:select>
                    </div>
                </div>


                <div class="flex flex-col md:flex-row md:items-center justify-between w-full gap-4 md:gap-0">
                    <div class="md:w-5/12">
                        <flux:input label="Due date" type="date"/>
                    </div>

                    <div class="md:w-5/12">
                        <flux:select variant="listbox" placeholder="Choose Owner..." label="Project Owner">
                            <flux:select.option>Jeyker</flux:select.option>
                        </flux:select>
                    </div>
                </div>

                <div class="flex">
                    <flux:modal.close>
                        <flux:button>Cancel</flux:button>
                    </flux:modal.close>

                    <flux:spacer/>

                    <flux:button type="submit" variant="primary">Create</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
