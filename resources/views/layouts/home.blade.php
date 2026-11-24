<x-layouts::home.body :title="$title ?? null">
    {{ $slot }}

    @auth
        <livewire:layout.quick-capture />
    @endauth
</x-layouts::home.body>
