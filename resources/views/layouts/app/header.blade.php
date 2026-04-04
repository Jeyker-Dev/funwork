@php
    $project = \App\Models\Projects::findOrFail($projectId);
@endphp

<flux:header container class="bg-white">
    <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left"/>

    <div class="flex gap-4 items-center">
        <x-projects.icon-project :project="$project"/>

        <flux:heading class="text-xl lg:text-2xl">
            {{ $project->name }}
        </flux:heading>
    </div>

    <flux:spacer/>


    <div class="flex items-center gap-2">
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
                    <flux:input icon="magnifying-glass" placeholder="Search Projects" size="sm" class="gray-search-2 mt-2 mb-4" wire:model.live="search"  />
                </flux:menu.group>

                <flux:menu.group heading="Projects">
                    <flux:menu.item class="flex items-center gap-2 hover:border-r-3 hover:border-r-orange-500 cursor-pointer">
                        <x-projects.icon-project
                            :project="$project"
                            iconClass="size-4"
                            containerClass="w-6! h-6!"
                        />

                        <flux:text>
                            {{ $project->name }}
                        </flux:text>
                    </flux:menu.item>
                </flux:menu.group>
            </flux:menu>

        </flux:dropdown>

        <flux:button icon="cog-6-tooth" variant="ghost" class="size-10!"/>

        <x-desktop-user-menu/>
    </div>
</flux:header>

<!-- Mobile Menu -->
<flux:sidebar collapsible="mobile" sticky
              class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
    <flux:sidebar.header>
        <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate/>
        <flux:sidebar.collapse
            class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2"/>
    </flux:sidebar.header>

    <flux:sidebar.nav>
        <flux:sidebar.group :heading="__('Platform')">
            <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                               wire:navigate>
                {{ __('Dashboard')  }}
            </flux:sidebar.item>
        </flux:sidebar.group>
    </flux:sidebar.nav>

    <flux:spacer/>

    <flux:sidebar.nav>
        <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
            {{ __('Repository') }}
        </flux:sidebar.item>
        <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
            {{ __('Documentation') }}
        </flux:sidebar.item>
    </flux:sidebar.nav>
</flux:sidebar>


