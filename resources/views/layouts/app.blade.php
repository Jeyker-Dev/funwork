<x-layouts::app.body :title="$title ?? null" :project="$project ?? request()->route('project')">
    <flux:main>
        {{ $slot }}
    </flux:main>

    @auth
        <livewire:layout.quick-capture :project="$project ?? request()->route('project')" />
    @endauth
</x-layouts::app.body>
