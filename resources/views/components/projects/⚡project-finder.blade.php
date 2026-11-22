<?php

use App\Models\Project;
use App\Support\QueryHelper;
use Livewire\Component;

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
            'projects' => Project::where('owner_id', auth()->id())
                ->whereRaw("name LIKE ? ESCAPE '!'", ['%' . QueryHelper::escapeLike($this->search) . '%'])
                ->where('id', '!=', $this->project->id)
                ->limit(3)
                ->get(),
        ];
    }
};
?>

<div>
    <flux:dropdown position="bottom" align="start">
        <flux:button
            variant="ghost"
            data-project-switcher
            class="rounded-full! bg-surface-card! dark:bg-surface-high! border border-ghost shadow-xs px-2.5! sm:px-3.5! py-1! h-10! max-md:min-h-11! flex items-center! justify-center! gap-2 max-w-[12.5rem] sm:max-w-xs"
            icon:trailing="chevron-down"
        >
            <div class="flex items-center gap-2 min-w-0">
                <x-projects.icon-project
                    :project="$project"
                    iconClass="size-4"
                    containerClass="w-6! h-6! rounded-full! shrink-0"
                />

                <span class="font-headline font-semibold text-sm text-ink truncate">
                    {{ $project->name }}
                </span>
            </div>
        </flux:button>

        <flux:menu>
            <flux:menu.group heading="Search Projects">
                <flux:input icon="magnifying-glass" placeholder="Search Projects" size="sm"
                            class="gray-search-2 mt-2 mb-4" wire:model.live="search"/>
            </flux:menu.group>

            <flux:menu.group heading="Projects">
                @foreach($projects as $project)
                    <a href="{{ route('projects.show', ['project' => $project]) }}">
                        <flux:menu.item
                            class="flex items-center gap-2 hover:border-r-3 hover:border-r-brand cursor-pointer">
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
