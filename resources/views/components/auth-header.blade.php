@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-3">
    <h1 class="font-manrope text-3xl font-extrabold leading-tight tracking-tight text-ink text-balance sm:text-4xl">{{ $title }}</h1>
    <p class="text-base text-ink-muted">{{ $description }}</p>
</div>
