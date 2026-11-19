@php
    $placeholder = '';

    if(request()->routeIs('project.tasks')) {
        $placeholder = 'Search tasks';
    }
@endphp

<flux:header class="bg-surface-low flex items-center justify-between gap-2 sm:gap-4 px-4 lg:px-8 xl:px-16">
    <div class="flex gap-2 sm:gap-3 items-center min-w-0">
        <flux:sidebar.toggle class="lg:hidden shrink-0 min-h-11 min-w-11" icon="bars-2" inset="left"/>

        <x-projects.icon-project :project="$project" containerClass="shrink-0 max-sm:w-9 max-sm:h-9"/>

        <flux:heading class="text-lg sm:text-xl lg:text-2xl truncate min-w-0">
            {{ $project->name }}
        </flux:heading>
    </div>

    @if(!request()->routeIs('projects.show'))
        <livewire:utilities.search-input
            :key="'header-search-desktop'"
            containerClass="hidden md:block min-w-0 flex-1 max-w-xs"
            inputPlaceholder="{{ $placeholder }}"
        />
    @endif

    <div class="flex items-center gap-1 sm:gap-2 shrink-0">
        @if(!request()->routeIs('projects.show'))
            <flux:dropdown position="bottom" align="end" class="md:hidden">
                <flux:button variant="ghost" icon="magnifying-glass" aria-label="Search" class="min-h-11 min-w-11" />

                <flux:menu class="w-72 p-3">
                    <livewire:utilities.search-input
                        :key="'header-search-mobile'"
                        containerClass="w-full"
                        inputPlaceholder="{{ $placeholder }}"
                    />
                </flux:menu>
            </flux:dropdown>
        @endif

        <livewire:projects.project-finder :project="$project"/>

        <livewire:projects.manage-areas :project="$project"/>

        <livewire:layout.notification-bell />

        <x-desktop-user-menu/>
    </div>
</flux:header>
