@props([
    'status',
    'count',
])

@php
    use App\Enums\TaskStatus;

    $badgeClasses = $status === TaskStatus::Done
        ? 'bg-emerald-100/70 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300'
        : 'bg-surface-high/70 text-ink-muted dark:bg-zinc-800';
@endphp

<section class="flex flex-col gap-2.5 rounded-xl border border-ghost bg-surface p-2.5 dark:bg-surface/70">
    <div class="flex items-center justify-between px-1">
        <h3 class="flex items-center gap-2 font-inter text-xs font-semibold text-ink">
            <span class="size-2 shrink-0 rounded-full {{ $status->dot() }}"></span>
            {{ $status->value }}
        </h3>

        <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $badgeClasses }}">
            {{ $count }}
        </span>
    </div>

    {{ $slot }}
</section>
