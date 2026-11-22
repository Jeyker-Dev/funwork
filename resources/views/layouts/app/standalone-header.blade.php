<flux:header class="bg-surface-low/90 dark:bg-surface-low/90 backdrop-blur-md px-4 lg:px-8 py-3 flex items-center justify-between gap-2 sm:gap-4">
    <x-app-logo name="FunWork" class="shrink-0 max-md:min-h-11" />

    @auth
        <div class="flex items-center gap-1 sm:gap-2 shrink-0">
            <livewire:layout.notification-bell />

            <x-desktop-user-menu />
        </div>
    @endauth
</flux:header>
