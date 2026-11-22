@php
    $user = auth()->user();
    $avatarUrl = $user->profile_photo_path ? $user->avatar_url : null;
@endphp

<flux:dropdown position="bottom" align="end">
    <button
        type="button"
        data-test="sidebar-menu-button"
        aria-label="{{ $user->display_name }}"
        class="group flex items-center gap-2.5 rounded-full py-1 pl-2.5 pr-1 max-md:min-h-11 max-md:min-w-11 max-md:justify-center max-md:px-1 hover:bg-surface-high/60 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand shrink-0 cursor-pointer"
    >
        <span class="hidden md:inline text-sm font-medium text-ink truncate max-w-32">
            {{ $user->display_name }}
        </span>

        <flux:avatar
            size="sm"
            circle
            :name="$user->display_name"
            :initials="$user->initials()"
            :src="$avatarUrl"
            class="bg-brand-strong! text-white! dark:bg-brand-strong! dark:text-white!"
        />
    </button>

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
                circle
                :name="$user->display_name"
                :initials="$user->initials()"
                :src="$avatarUrl"
                class="bg-brand-strong! text-white! dark:bg-brand-strong! dark:text-white!"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ $user->display_name }}</flux:heading>
                <flux:text class="truncate">{{ $user->email }}</flux:text>
            </div>
        </div>

        <flux:menu.separator />

        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
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
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
