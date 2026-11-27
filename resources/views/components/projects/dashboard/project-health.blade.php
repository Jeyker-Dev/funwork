@props(['health'])

@php
    use App\Actions\Projects\GetProjectHealthAction;

    $totalCount = $health->count();
    $maxVisible = GetProjectHealthAction::DEFAULT_VISIBLE_CHECKS;
    $hasOverflow = $totalCount > $maxVisible;
    $highestSeverity = $health->checks->first()?->severity;
@endphp

@if($health->checks->isNotEmpty())
    <section
        aria-labelledby="project-health-heading"
        x-data="{ expanded: false }"
        class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-6 flex flex-col gap-4"
    >
        <div class="flex flex-wrap items-center gap-3">
            <div class="size-9 rounded-xl bg-surface-low text-accent-content flex items-center justify-center shrink-0">
                <flux:icon name="heart" class="size-5" aria-hidden="true" />
            </div>

            <div class="flex flex-wrap items-center gap-2.5 min-w-0">
                <h2 id="project-health-heading" class="font-headline font-bold text-lg sm:text-xl text-ink tracking-tight">
                    Project health
                </h2>

                @if($highestSeverity !== null)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $highestSeverity->badgeClasses() }}">
                        {{ $totalCount === 1 ? '1 item needs attention' : "{$totalCount} items need attention" }}
                    </span>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            @foreach($health->checks as $index => $check)
                @php
                    $isOverflow = $index >= $maxVisible;
                @endphp

                <a
                    wire:key="health-check-{{ $check->key }}"
                    data-health-check
                    data-severity="{{ $check->severity->value }}"
                    href="{{ $check->linkUrl }}"
                    wire:navigate
                    @if($isOverflow)
                        x-show="expanded"
                        x-cloak
                    @endif
                    class="group flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-4 p-3 -mx-2 rounded-xl hover:bg-surface-low transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand max-md:min-h-11"
                >
                    <div class="flex items-start sm:items-center gap-3 min-w-0">
                        <div class="size-8 rounded-full flex items-center justify-center shrink-0 mt-0.5 sm:mt-0 {{ $check->severity->iconClasses() }}">
                            <flux:icon :name="$check->severity->icon()" class="size-4" aria-hidden="true" />
                        </div>

                        <div class="flex flex-col min-w-0">
                            <span class="text-sm font-semibold text-ink leading-snug break-words">
                                {{ $check->title }}
                            </span>
                            <span class="text-xs text-ink-muted mt-0.5 truncate">
                                {{ $check->explanation }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 text-xs font-semibold text-accent-content group-hover:underline pl-11 sm:pl-0 shrink-0">
                        <span>{{ $check->linkLabel }}</span>
                        <flux:icon name="chevron-right" class="size-4 shrink-0" aria-hidden="true" />
                    </div>
                </a>
            @endforeach
        </div>

        @if($hasOverflow)
            <div>
                <button
                    type="button"
                    x-on:click="expanded = !expanded"
                    x-bind:aria-expanded="expanded.toString()"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-accent-content hover:underline rounded-lg px-2 py-1.5 -mx-2 max-md:min-h-11 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    <span x-show="!expanded">Show all ({{ $totalCount }})</span>
                    <span x-show="expanded" x-cloak>Show fewer</span>
                    <flux:icon name="chevron-down" x-show="!expanded" class="size-3.5" aria-hidden="true" />
                    <flux:icon name="chevron-up" x-show="expanded" x-cloak class="size-3.5" aria-hidden="true" />
                </button>
            </div>
        @endif
    </section>
@endif
