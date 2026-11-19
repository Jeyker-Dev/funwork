<?php

use Livewire\Component;
use App\Models\Project;
use Livewire\Attributes\On;

new class extends Component
{
    public function with(): array
    {
        return [
            'projects' => Project::where('owner_id', auth()->id())->get(),
            'elementName' => 'header',
        ];
    }

        #[On('project-created')]
    public function refreshProjects(): void
    {
        $this->with();
    }
};
?>

<div>
    <flux:header class="bg-surface-low px-4 lg:px-8 py-3 flex items-center justify-between gap-2 sm:gap-4">
        <x-app-logo name="FunWork" class="shrink-0 max-md:min-h-11"/>

        <livewire:utilities.search-input
            :key="'projects-search-desktop'"
            containerClass="-mb-px hidden md:block min-w-0 flex-1 max-w-md mx-2"
            inputPlaceholder="Search Projects"
        />

        <div class="flex items-center gap-1 sm:gap-2 shrink-0">
            <flux:dropdown position="bottom" align="end" class="md:hidden">
                <flux:button variant="ghost" icon="magnifying-glass" aria-label="Search Projects" class="min-h-11 min-w-11" />

                <flux:menu class="w-72 p-3">
                    <livewire:utilities.search-input
                        :key="'projects-search-mobile'"
                        containerClass="w-full"
                        inputPlaceholder="Search Projects"
                    />
                </flux:menu>
            </flux:dropdown>

            @if(!$projects->isEmpty())
                <livewire:projects.create-project :elementName="$elementName"/>
            @endif

            <livewire:layout.notification-bell />

            <!-- User Dropdown content -->
            <x-desktop-user-menu/>
        </div>
    </flux:header>
</div>
