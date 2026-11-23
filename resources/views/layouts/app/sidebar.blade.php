<flux:sidebar sticky collapsible="mobile" class="bg-surface-low">
    <flux:sidebar.header>
        <x-app-logo :sidebar="true" href="{{ route('projects') }}" wire:navigate />
        <flux:sidebar.collapse class="lg:hidden" />
    </flux:sidebar.header>

    <flux:sidebar.nav>
        <flux:sidebar.group class="flex flex-col gap-1.5">
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
                :current="request()->routeIs('project.inbox')"
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
                :current="request()->routeIs('project.notebooks*')"
                wire:navigate
            >
                {{ __('Notebooks') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="scale"
                :href="route('project.decisions', ['project' => $project])"
                :current="request()->routeIs('project.decisions*')"
                wire:navigate
            >
                {{ __('Decisions') }}
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="clock"
                :href="route('project.history', ['project' => $project])"
                :current="request()->routeIs('project.history')"
                wire:navigate
            >
                {{ __('History') }}
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

    @php
        $latestReleaseDate = app(\App\Services\ChangelogService::class)->latestReleaseDate();
        $hasUnreadChangelog = auth()->user()?->hasUnreadChangelog($latestReleaseDate) ?? false;
    @endphp

    <flux:sidebar.nav>
        <flux:separator class="mb-3"/>

        <flux:sidebar.item
            icon="sparkles"
            :href="route('whats-new')"
            :current="request()->routeIs('whats-new')"
            :tooltip="__('What\'s new')"
            wire:navigate
        >
            {{ __("What's new") }}

            @if($hasUnreadChangelog)
                <x-slot:iconTrailing>
                    <span
                        class="size-2 rounded-full bg-brand shrink-0"
                        role="img"
                        aria-label="{{ __('New updates') }}"
                    ></span>
                </x-slot:iconTrailing>
            @endif
        </flux:sidebar.item>

        <flux:text class="mt-2 text-center text-[11px] text-ink-subtle/75">
            Built with <span class="text-red-500/75 dark:text-red-400/75" aria-hidden="true">♥</span> by Jeyker-Dev
        </flux:text>
    </flux:sidebar.nav>
</flux:sidebar>
