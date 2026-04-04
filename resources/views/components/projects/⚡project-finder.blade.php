<?php

use Livewire\Component;
use App\Models\Project;

new class extends Component {
    public Project $project;
    public string $search = '';

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function with(): array
    {
        return [
            'projects' => Project::where('name', 'like', '%' . $this->search . '%')
            ->limit(3)
            ->get(),
        ];
    }
};
?>

<div>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" class="flex items-center! justify-center!" icon:trailing="chevron-down">
            <div class="flex items-center gap-2">
                <x-projects.icon-project :project="$project" iconClass="size-4 text-gray-500!"
                                         containerClass="w-6! h-6! bg-transparent!"/>

                <flux:heading class="text-sm">
                    {{ $project->name }}
                </flux:heading>
            </div>
        </flux:button>


        <flux:menu>
            <flux:menu.group heading="Search Projects">
                <flux:input icon="magnifying-glass" placeholder="Search Projects" size="sm"
                            class="gray-search-2 mt-2 mb-4" wire:model.live="search"/>
            </flux:menu.group>

            <flux:menu.group heading="Projects">
                @foreach($projects as $project)
                    <a href="{{ route('projects.show', ['project' => $project->id]) }}">
                        <flux:menu.item
                            class="flex items-center gap-2 hover:border-r-3 hover:border-r-orange-500 cursor-pointer">
                            <x-projects.icon-project
                                :project="$project"
                                iconClass="size-4"
                                containerClass="w-6! h-6!"
                            />

                            <flux:text>
                                {{ $project->name }}
                            </flux:text>
                        </flux:menu.item>
                    </a>
                @endforeach
            </flux:menu.group>
        </flux:menu>

    </flux:dropdown>
</div>
