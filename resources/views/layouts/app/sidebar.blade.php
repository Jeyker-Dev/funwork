<flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
    <flux:sidebar.header>
        <x-app-logo :sidebar="true" href="{{ route('projects') }}" wire:navigate />
        <flux:sidebar.collapse class="lg:hidden" />
    </flux:sidebar.header>

    <flux:sidebar.nav>
        <flux:sidebar.group class="flex flex-col gap-4">
            <flux:sidebar.item
                icon="home"
                :href="route('projects.show', ['project' => $project])"
                :current="request()->routeIs('projects.show', ['project' => $project])"
                wire:navigate
            >
                {{ __('Home') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="inbox"
                :href="route('project.inbox', ['project' => $project])"
                :current="request()->routeIs('project.inbox', ['project' => $project])"
                wire:navigate
            >
                {{ __('Inbox') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="rectangle-stack"
                :href="route('project.tasks', ['project' => $project])"
                :current="request()->routeIs('project.tasks' , ['project' => $project])"
                wire:navigate
            >
                {{ __('Tasks') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="clipboard-document-list"
                :href="route('project.notebooks', ['project' => $project])"
                :current="request()->routeIs('project.notebooks' , ['project' => $project])"
                wire:navigate
            >
                {{ __('Notebooks') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="folder"
                :href="route('project.files', ['project' => $project])"
                :current="request()->routeIs('project.files' , ['project' => $project])"
                wire:navigate
            >
                {{ __('Files') }}
            </flux:sidebar.item>
        </flux:sidebar.group>
    </flux:sidebar.nav>

    <flux:spacer />

    <flux:sidebar.nav>
        <flux:separator class="mb-4"/>

        <flux:button icon="bell" variant="ghost">Change Log</flux:button>

        <flux:text class="mt-2 text-center text-xs">
            Built with <span class="text-red-500 text-xl">♥</span> by Jeyker-Dev
        </flux:text>
    </flux:sidebar.nav>
</flux:sidebar>

<!-- Mobile User Menu -->
<flux:header class="lg:hidden">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

    <flux:spacer />

    <flux:dropdown position="top" align="end">
        <flux:profile
            :initials="auth()->user()->initials()"
            :avatar="auth()->user()->avatar_url"
            icon-trailing="chevron-down"
        />

        <flux:menu>
            <flux:menu.radio.group>
                <div class="p-0 text-sm font-normal">
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar
                            :name="auth()->user()->display_name"
                            :initials="auth()->user()->initials()"
                            :src="auth()->user()->avatar_url"
                        />

                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ auth()->user()->display_name }}</flux:heading>
                            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                        </div>
                    </div>
                </div>
            </flux:menu.radio.group>

            <flux:menu.separator />

            <flux:menu.radio.group>
                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                    {{ __('Settings') }}
                </flux:menu.item>
            </flux:menu.radio.group>

            <flux:menu.separator />

            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
</flux:header>
