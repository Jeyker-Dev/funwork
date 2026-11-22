@props([
    'area',
    'areaClasses',
    'title',
    'initials',
    'avatarClasses',
])

<article {{ $attributes->class(['flex flex-col justify-between gap-2 rounded-xl border border-ghost bg-surface-card p-3 shadow-xs dark:bg-surface-high']) }}>
    <div class="flex items-center justify-between gap-2">
        <span class="rounded border px-2 py-0.5 text-[10px] font-semibold {{ $areaClasses }}">
            {{ $area }}
        </span>

        {{ $meta ?? '' }}
    </div>

    <p class="font-manrope text-xs font-bold leading-snug text-ink">
        {{ $title }}
    </p>

    {{ $progress ?? '' }}

    <div class="flex items-center justify-between gap-2 pt-0.5 text-[11px]">
        {{ $footer ?? '' }}

        <span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full text-[9px] font-bold {{ $avatarClasses }}">
            {{ $initials }}
        </span>
    </div>
</article>
