<x-layouts::app.body :title="$title ?? null" :project="$project ?? null">
    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts::app.body>
