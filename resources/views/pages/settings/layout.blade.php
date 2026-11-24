@props([
    'heading' => null,
    'subheading' => null,
])

@php
    $navItems = [
        ['label' => __('Profile'), 'route' => 'profile.edit', 'icon' => 'user-circle'],
        ['label' => __('Security'), 'route' => 'security.edit', 'icon' => 'shield-check'],
        ['label' => __('Appearance'), 'route' => 'appearance.edit', 'icon' => 'swatch'],
        ['label' => __('Notifications'), 'route' => 'notifications.edit', 'icon' => 'bell'],
        ['label' => __('Integrations'), 'route' => 'integrations.edit', 'icon' => 'puzzle-piece'],
    ];
@endphp

<div class="flex flex-col lg:flex-row gap-8 items-start">
    <aside class="w-full min-w-0 lg:w-[240px] shrink-0">
        <nav
            aria-label="{{ __('Settings') }}"
            x-data
            x-init="$nextTick(() => $el.querySelector('[aria-current=page]')?.scrollIntoView({ block: 'nearest', inline: 'nearest' }))"
            class="flex lg:flex-col gap-1.5 overflow-x-auto lg:overflow-visible pb-2 lg:pb-0"
        >
            @foreach ($navItems as $item)
                @php
                    $isCurrent = request()->routeIs($item['route']);
                @endphp
                <a
                    href="{{ route($item['route']) }}"
                    wire:navigate
                    @if ($isCurrent)
                        aria-current="page"
                        data-current="true"
                    @endif
                    data-flux-navlist-item
                    @class([
                        'flex items-center gap-3 px-4 py-3 min-h-11 rounded-xl text-sm whitespace-nowrap shrink-0 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                        'font-semibold' => $isCurrent,
                        'text-ink-muted hover:text-ink hover:bg-surface-low font-medium' => ! $isCurrent,
                    ])
                >
                    <flux:icon :name="$item['icon']" class="size-5 shrink-0" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </aside>

    <div class="flex-1 w-full min-w-0 flex flex-col gap-6">
        {{ $slot }}
    </div>
</div>
