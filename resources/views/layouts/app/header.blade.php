@php
    $project = \App\Models\Project::findOrFail($projectId);
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
        <livewire:projects.project-finder :project="$project"/>

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
