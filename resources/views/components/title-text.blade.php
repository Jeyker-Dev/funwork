@props([
    'title' => null,
    'highlight' => null,
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'mb-8']) }}>
    @if ($title || $highlight)
        <h1 class="text-xl md:text-4xl lg:text-5xl font-bold mb-4">
            {{ $title }}
            
            @if ($title && $highlight)
                <br>
            @endif
            
            @if ($highlight)
                <span class="text-orange-500 mt-4 block">{{ $highlight }}</span>
            @endif
        </h1>
    @endif

    @if ($description)
        <p class="text-gray-500 font-normal text-sm md:text-base max-w-96 md:max-w-lg">
            {{ $description }}
        </p>
    @endif

    {{ $slot }}
</div>
