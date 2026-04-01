<x-layouts::app.body :title="$title ?? null" :projectId="$projectId">
    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts::app.body>
