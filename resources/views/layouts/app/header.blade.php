@php
    $placeholder = '';

    if(request()->routeIs('project.tasks')) {
        $placeholder = 'Search tasks';
    }
@endphp

<flux:header class="bg-surface-low/90 dark:bg-surface-low/90 backdrop-blur-md flex items-center justify-between gap-2 sm:gap-4 px-3! sm:px-6! lg:px-8!">
    <div data-header-left class="flex gap-1.5 sm:gap-3 items-center min-w-0 flex-1">
        <flux:sidebar.toggle class="lg:hidden shrink-0 min-h-11 min-w-11" icon="bars-2" inset="left"/>

        <livewire:projects.project-finder :project="$project"/>
    </div>

    @if(!request()->routeIs('projects.show'))
        <livewire:utilities.search-input
            :key="'header-search-desktop'"
            containerClass="hidden md:block min-w-0 flex-1 max-w-xs"
            inputPlaceholder="{{ $placeholder }}"
        />
    @endif

    <div data-header-right class="flex items-center gap-0.5 sm:gap-2 shrink-0">
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

        <livewire:projects.manage-areas :project="$project"/>

        <livewire:layout.notification-bell />

        <x-desktop-user-menu/>
    </div>
</flux:header>
