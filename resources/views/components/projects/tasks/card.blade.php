@props(['area' => null])

<div {{ $attributes->class(['backdrop-blur-md border rounded-xl px-6 py-5']) }}>
    @if($area)
        <div class="mb-2">
            <flux:badge size="sm" :color="$area->color">{{ $area->name }}</flux:badge>
        </div>
    @endif
    {{ $slot }}
</div>
