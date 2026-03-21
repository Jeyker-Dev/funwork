@props([
    'method' => 'POST',
    'action' => '#',
])

<form method="{{ $method }}" action="{{ $action }}" {{ $attributes->merge(['class' => 'bg-white p-8 rounded-md flex flex-col gap-6 form-line relative']) }}>
    {{ $slot }}
</form>
