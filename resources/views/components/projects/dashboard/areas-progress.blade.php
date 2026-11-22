@props(['areas', 'project'])

@php
    use App\Enums\AreaColor;
    use Illuminate\Support\Str;
@endphp

<section aria-labelledby="areas-heading" class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-6 flex flex-col gap-5">
    <div class="flex items-center justify-between gap-3">
        <h2 id="areas-heading" class="font-headline font-bold text-lg text-ink tracking-tight">Areas</h2>

        @if($areas->isNotEmpty())
            <span class="text-xs font-medium text-ink-muted">
                {{ $areas->count() }} {{ Str::plural('area', $areas->count()) }}
            </span>
        @endif
    </div>

    @if($areas->isEmpty())
        <div class="rounded-xl bg-surface-low p-6 text-center space-y-3">
            <div class="inline-flex items-center justify-center size-10 rounded-full bg-surface-card text-ink-subtle mx-auto">
                <flux:icon name="squares-2x2" class="size-5" />
            </div>

            <div class="space-y-1">
                <p class="font-headline font-semibold text-base text-ink">No areas yet</p>
                <p class="text-sm text-ink-muted">Organize tasks and notebooks into project areas.</p>
            </div>

            <flux:button
                size="sm"
                variant="ghost"
                x-on:click="Flux.modal('manage-areas-modal').show()"
                class="max-md:min-h-11"
            >
                Manage areas
            </flux:button>
        </div>
    @else
        <div class="flex flex-col gap-2">
            @foreach($areas as $area)
                @php
                    $total = (int) $area->tasks_count;
                    $done = (int) $area->done_tasks_count;
                    $percent = $total > 0 ? (int) round(($done / $total) * 100) : 0;
                    $isComplete = $total > 0 && $done === $total;
                @endphp

                <a
                    wire:key="home-area-{{ $area->id }}"
                    href="{{ route('project.tasks', ['project' => $project, 'area' => $area->slug]) }}"
                    wire:navigate
                    class="block p-2.5 -mx-2.5 rounded-xl hover:bg-surface-low transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    <div class="flex items-center justify-between gap-3 text-sm mb-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="size-2.5 rounded-full shrink-0 {{ AreaColor::dotClassFor($area->color) }}" aria-hidden="true"></span>
                            <span class="font-medium text-ink truncate">{{ $area->name }}</span>
                        </div>

                        <span class="text-xs font-medium shrink-0 {{ $isComplete ? 'text-emerald-700 dark:text-emerald-400' : 'text-ink-muted' }}">
                            {{ $done }} of {{ $total }} tasks done
                        </span>
                    </div>

                    <div class="w-full h-1.5 bg-surface-highest rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full {{ AreaColor::progressBarClassFor($area->color) }}"
                            style="width: {{ $percent }}%"
                        ></div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>
