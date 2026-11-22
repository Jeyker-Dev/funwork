@props([
    'sidebar' => false,
    'name' => 'FunWork',
])

@if($sidebar)
    <flux:sidebar.brand name="{{ $name }}" {{ $attributes->class('h-12! px-2.5! gap-3!') }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center rounded-xl bg-accent-content text-accent-foreground shadow-xs">
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand href="{{ route('projects') }}" name="{{ $name }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        </x-slot>
    </flux:brand>
@endif
