<x-layouts::app.body :title="$title ?? null" :projectId="$projectId ?? 1">
    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts::app.body>
