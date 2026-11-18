<?php

use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.home', ['title' => 'Notifications'])] class extends Component
{
    use WithPagination;

    private const int PER_PAGE = 15;

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        return Auth::user()
            ->notifications()
            ->latest()
            ->paginate(self::PER_PAGE);
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
        Flux::toast(__('All notifications marked as read.'));
    }

    #[On('notifications-updated')]
    public function refreshNotifications(): void
    {
        $this->resetCache();
    }

    private function resetCache(): void
    {
        unset($this->unreadCount, $this->notifications);
    }
};
?>

<div class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Notifications') }}</flux:heading>
            <flux:subheading class="mt-1">
                {{ __('Review your daily workload digests and recent alerts.') }}
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            @if ($this->unreadCount > 0)
                <flux:button variant="subtle" icon="check" wire:click="markAllAsRead">
                    {{ __('Mark all as read') }}
                </flux:button>
            @endif

            <flux:button variant="ghost" icon="cog-6-tooth" :href="route('notifications.edit')" wire:navigate>
                {{ __('Settings') }}
            </flux:button>
        </div>
    </div>

    @if ($this->notifications->isEmpty())
        <flux:card class="py-12 text-center">
            <flux:heading>{{ __('No notifications yet') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">
                {{ __('Your morning digests and system notifications will appear here.') }}
            </flux:text>
        </flux:card>
    @else
        <div class="space-y-4">
            @foreach ($this->notifications as $notification)
                @php
                    $data = is_array($notification->data) ? $notification->data : [];
                    $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
                    $isUnread = $notification->read_at === null;
                @endphp

                <flux:card wire:key="notification-card-{{ $notification->id }}" class="space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <flux:heading size="lg">
                                    {{ $data['title'] ?? __('Notification') }}
                                </flux:heading>

                                @if ($isUnread)
                                    <flux:badge size="sm" color="blue">{{ __('Unread') }}</flux:badge>
                                @endif
                            </div>

                            @if (! empty($data['summary']))
                                <flux:text>{{ $data['summary'] }}</flux:text>
                            @endif

                            <flux:text variant="subtle" class="text-xs">
                                {{ $notification->created_at->diffForHumans() }}
                            </flux:text>
                        </div>

                        @if ($isUnread)
                            <flux:button
                                variant="subtle"
                                size="sm"
                                wire:click="markAsRead('{{ $notification->id }}')"
                            >
                                {{ __('Mark as read') }}
                            </flux:button>
                        @endif
                    </div>

                    @if ($sections !== [])
                        <div class="grid gap-4 border-t border-zinc-200 pt-4 sm:grid-cols-2 dark:border-white/10">
                            @foreach ([
                                'overdue_tasks' => ['label' => __('Overdue'), 'color' => 'red'],
                                'today_tasks' => ['label' => __('Due today'), 'color' => 'amber'],
                                'upcoming_tasks' => ['label' => __('Due in 3 days'), 'color' => 'blue'],
                            ] as $key => $meta)
                                @if (! empty($sections[$key]))
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <flux:badge size="sm" :color="$meta['color']">{{ $meta['label'] }}</flux:badge>
                                            <flux:text variant="subtle" class="text-xs">{{ count($sections[$key]) }}</flux:text>
                                        </div>

                                        <ul class="space-y-1.5">
                                            @foreach ($sections[$key] as $taskItem)
                                                <li class="text-sm">
                                                    <a
                                                        href="{{ $taskItem['url'] }}"
                                                        wire:navigate
                                                        class="font-medium text-zinc-900 hover:underline dark:text-white"
                                                    >
                                                        {{ $taskItem['title'] }}
                                                    </a>
                                                    <span class="text-xs text-zinc-500">
                                                        · {{ $taskItem['project_name'] }}
                                                        @if (! empty($taskItem['due_date']))
                                                            ({{ $taskItem['due_date'] }})
                                                        @endif
                                                    </span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach

                            @if (! empty($sections['inbox_items']))
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <flux:badge size="sm" color="zinc">{{ __('Inbox') }}</flux:badge>
                                        <flux:text variant="subtle" class="text-xs">{{ count($sections['inbox_items']) }}</flux:text>
                                    </div>

                                    <ul class="space-y-1.5">
                                        @foreach ($sections['inbox_items'] as $inboxEntry)
                                            <li class="text-sm">
                                                <a
                                                    href="{{ $inboxEntry['url'] }}"
                                                    wire:navigate
                                                    class="font-medium text-zinc-900 hover:underline dark:text-white"
                                                >
                                                    {{ \Illuminate\Support\Str::limit($inboxEntry['content'], 80) }}
                                                </a>
                                                <span class="text-xs text-zinc-500">
                                                    · {{ $inboxEntry['project_name'] }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $this->notifications->links() }}
        </div>
    @endif
</div>
