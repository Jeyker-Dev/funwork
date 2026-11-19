@props([
    'method' => 'POST',
    'action' => '#',
])

<form method="{{ $method }}" action="{{ $action }}" {{ $attributes->merge(['class' => 'bg-surface-card p-6 sm:p-8 rounded-2xl shadow-ambient border border-ghost flex flex-col gap-6 form-line relative overflow-hidden']) }}>
    {{ $slot }}
</form>
