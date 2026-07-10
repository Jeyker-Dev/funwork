<x-layouts::app.body :title="$title ?? null" :project="$project ?? request()->route('project')">
    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts::app.body>
