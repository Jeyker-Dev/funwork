@props([
    'method' => 'POST',
    'action' => '#',
])

<form method="{{ $method }}" action="{{ $action }}" {{ $attributes->merge(['class' => 'flex flex-col gap-6']) }}>
    {{ $slot }}
</form>
