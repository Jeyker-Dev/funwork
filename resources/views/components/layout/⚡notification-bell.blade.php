<?php

use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    private const int DROPDOWN_LIMIT = 5;

    private const int MAX_BADGE_COUNT = 99;

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    #[Computed]
    public function notifications(): DatabaseNotificationCollection
    {
        return Auth::user()
            ->notifications()
            ->latest()
            ->take(self::DROPDOWN_LIMIT)
            ->get();
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = Auth::user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        $notification->markAsRead();

        $this->resetCache();
        $this->dispatch('notifications-updated');
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();

        $this->resetCache();
        $this->dispatch('notifications-updated');
    }

    #[On('notifications-updated')]
    #[On('notification-received')]
    public function refreshNotifications(): void
    {
        $this->resetCache();
    }

    public function badgeLabel(): string
    {
        $count = $this->unreadCount;

        return $count > self::MAX_BADGE_COUNT ? self::MAX_BADGE_COUNT.'+' : (string) $count;
    }

    private function resetCache(): void
    {
        unset($this->unreadCount, $this->notifications);
    }
};
?>

<div>
    <flux:dropdown position="bottom" align="end">
        <flux:button
            variant="subtle"
            class="relative"
            aria-label="{{ __('Notifications') }}"
            data-test="notification-bell-button"
        >
            <flux:icon.bell class="size-5" />

            @if ($this->unreadCount > 0)
                <flux:badge size="sm" color="red" class="-ms-1">
                    {{ $this->badgeLabel() }}
                </flux:badge>
            @endif
        </flux:button>

        <flux:menu class="w-80 p-0 sm:w-96">
            <div class="flex items-center justify-between gap-2 px-3 py-2.5">
                <flux:heading size="sm">{{ __('Notifications') }}</flux:heading>

                @if ($this->unreadCount > 0)
                    <flux:button
                        variant="ghost"
                        size="xs"
                        wire:click="markAllAsRead"
                    >
                        {{ __('Mark all as read') }}
                    </flux:button>
                @endif
            </div>

            <flux:separator />

            @if ($this->notifications->isEmpty())
                <div class="px-4 py-6 text-center">
                    <flux:text variant="subtle">{{ __('No notifications yet.') }}</flux:text>
                </div>
            @else
                <div class="max-h-80 divide-y divide-zinc-200 overflow-y-auto dark:divide-white/10">
                    @foreach ($this->notifications as $notification)
                        @php
                            $data = is_array($notification->data) ? $notification->data : [];
                            $isUnread = $notification->read_at === null;
                        @endphp

                        <div
                            wire:key="bell-notification-{{ $notification->id }}"
                            class="flex flex-col gap-1.5 px-3 py-2.5 {{ $isUnread ? 'bg-zinc-50/80 dark:bg-white/5' : '' }}"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <a
                                    href="{{ $data['url'] ?? route('notifications.index') }}"
                                    wire:navigate
                                    class="min-w-0 flex-1 hover:underline"
                                >
                                    <flux:text class="font-medium {{ $isUnread ? 'text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-400' }}">
                                        {{ $data['title'] ?? __('Notification') }}
                                    </flux:text>
                                </a>

                                @if ($isUnread)
                                    <flux:badge size="sm" color="blue">{{ __('New') }}</flux:badge>
                                @endif
                            </div>

                            @if (! empty($data['summary']))
                                <flux:text variant="subtle" class="text-xs">
                                    {{ $data['summary'] }}
                                </flux:text>
                            @endif

                            <div class="flex items-center justify-between gap-2">
                                <flux:text variant="subtle" class="text-xs">
                                    {{ $notification->created_at->diffForHumans() }}
                                </flux:text>

                                @if ($isUnread)
                                    <flux:button
                                        variant="ghost"
                                        size="xs"
                                        wire:click="markAsRead('{{ $notification->id }}')"
                                    >
                                        {{ __('Mark as read') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <flux:separator />

            <div class="px-3 py-2 text-center">
                <a
                    href="{{ route('notifications.index') }}"
                    wire:navigate
                    class="text-sm font-medium text-zinc-700 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white"
                >
                    {{ __('View all notifications') }}
                </a>
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
