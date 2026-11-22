<?php

use App\Actions\Projects\ListProjectHistoryAction;
use App\Enums\ActivityType;
use App\Enums\AreaColor;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')]
#[Title('History')]
class extends Component
{
    public Project $project;

    #[Url(as: 'type', except: '')]
    public string $typeFilter = '';

    public int $weeksLimit = ListProjectHistoryAction::DEFAULT_WEEKS_LIMIT;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
    }

    public function selectType(string $type = ''): void
    {
        if ($type === '' || ActivityType::tryFrom($type) !== null) {
            $this->typeFilter = $type;
            $this->weeksLimit = ListProjectHistoryAction::DEFAULT_WEEKS_LIMIT;
        }
    }

    public function showEarlierWeeks(): void
    {
        $this->weeksLimit += ListProjectHistoryAction::WEEKS_STEP;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(ListProjectHistoryAction $listProjectHistory): array
    {
        $filter = ActivityType::tryFrom($this->typeFilter);
        $history = $listProjectHistory($this->project, $filter, $this->weeksLimit, auth()->user());

        $filters = [
            ['value' => '', 'label' => 'All'],
            ['value' => ActivityType::Task->value, 'label' => 'Tasks'],
            ['value' => ActivityType::Decision->value, 'label' => 'Decisions'],
            ['value' => ActivityType::Notebook->value, 'label' => 'Notebooks'],
            ['value' => ActivityType::File->value, 'label' => 'Files'],
        ];

        return [
            'weeks' => $history->weeks,
            'hasMoreWeeks' => $history->hasMoreWeeks,
            'hasAnyEvents' => $history->hasAnyEvents,
            'activeFilter' => $filter,
            'filters' => $filters,
        ];
    }
};
?>

<div class="max-w-5xl mx-auto w-full py-2 sm:py-4 space-y-6">
    <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div class="min-w-0">
            <h1 class="font-headline font-bold text-2xl sm:text-3xl tracking-tight text-ink">
                {{ __('History') }}
            </h1>
            <p class="text-sm text-ink-muted mt-1">
                {{ __('How this project moved forward.') }}
            </p>
        </div>

        <nav
            data-history-filters
            aria-label="{{ __('Filter history by type') }}"
            class="flex flex-wrap items-center gap-1 bg-surface-low p-1 rounded-xl border border-ghost self-start lg:self-auto"
        >
            @foreach ($filters as $filterOption)
                @php
                    $isActive = $typeFilter === $filterOption['value'];
                    $chipKey = $filterOption['value'] === '' ? 'all' : $filterOption['value'];
                @endphp
                <button
                    type="button"
                    wire:key="history-chip-{{ $chipKey }}"
                    data-history-chip="{{ $chipKey }}"
                    wire:click="selectType('{{ $filterOption['value'] }}')"
                    aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                    @class([
                        'inline-flex items-center whitespace-nowrap px-3 py-1.5 max-md:min-h-11 rounded-lg text-xs font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                        'bg-ink text-surface-card shadow-xs dark:bg-zinc-100 dark:text-zinc-900' => $isActive,
                        'text-ink-muted hover:text-ink' => ! $isActive,
                    ])
                >
                    {{ __($filterOption['label']) }}
                </button>
            @endforeach
        </nav>
    </header>

    @if (! $hasAnyEvents)
        <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient text-center px-6 py-16 space-y-3">
            <div class="size-12 rounded-2xl bg-surface-low flex items-center justify-center mx-auto text-ink-subtle">
                <flux:icon.clock class="size-6" />
            </div>

            <div class="space-y-1.5 max-w-md mx-auto">
                <h2 class="font-headline font-bold text-lg text-ink">
                    {{ __('Nothing has happened yet') }}
                </h2>
                <p class="text-sm text-ink-muted">
                    {{ __('Completed tasks, accepted decisions, new notebooks, and uploaded files will appear here.') }}
                </p>
            </div>
        </div>
    @elseif ($weeks->isEmpty())
        <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient text-center px-6 py-14 space-y-3">
            <div class="size-11 rounded-2xl bg-surface-low flex items-center justify-center mx-auto text-ink-subtle">
                <flux:icon.clock class="size-5" />
            </div>
            <h2 class="font-headline font-bold text-lg text-ink">{{ __('No matching history') }}</h2>
            <p class="text-sm text-ink-muted">
                {{ __('There are no recorded events for this filter yet.') }}
            </p>
            <div class="pt-1">
                <flux:button variant="outline" size="sm" wire:click="selectType('')" class="max-md:min-h-11 bg-surface-card! border-ghost!">
                    {{ __('Show all') }}
                </flux:button>
            </div>
        </div>
    @else
        <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-8 space-y-8">
            @foreach ($weeks as $week)
                <section wire:key="history-week-{{ $week->weekKey }}" class="space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-1">
                        <div class="flex items-baseline gap-2.5 min-w-0">
                            <h2 class="font-headline font-bold text-lg text-ink">
                                {{ $week->title }}
                            </h2>
                            @if ($week->dateRange)
                                <span class="text-sm text-ink-subtle shrink-0">
                                    {{ $week->dateRange }}
                                </span>
                            @endif
                        </div>

                        <span class="inline-flex items-center self-start sm:self-auto text-xs font-medium text-ink-muted bg-surface-low px-3 py-1 rounded-full">
                            {{ $week->summary }}
                        </span>
                    </div>

                    <div class="space-y-1">
                        @foreach ($week->events as $event)
                            <article
                                wire:key="history-event-{{ $event->type->value }}-{{ $event->id }}"
                                class="flex items-start sm:items-center justify-between gap-3 p-3 -mx-2 rounded-xl hover:bg-surface-low transition-colors"
                            >
                                <div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                                    <span class="size-8 rounded-full flex items-center justify-center shrink-0 {{ $event->iconCircleClasses() }}" aria-hidden="true">
                                        <flux:icon :icon="$event->icon()" class="size-4" />
                                    </span>

                                    <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1 text-sm text-ink-muted min-w-0">
                                        <span class="break-words">
                                            {{ $event->actionVerb }}
                                            <a
                                                href="{{ $event->url }}"
                                                @if ($event->useWireNavigate) wire:navigate @endif
                                                class="font-medium text-ink hover:text-accent-content underline decoration-surface-highest underline-offset-4 hover:decoration-current transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded"
                                            >{{ $event->linkText }}</a>
                                        </span>

                                        @if ($event->areaName !== null)
                                            <span class="inline-flex items-center gap-1.5 text-xs text-ink-muted shrink-0">
                                                <span class="size-1.5 rounded-full shrink-0 {{ AreaColor::dotClassFor($event->areaColor) }}" aria-hidden="true"></span>
                                                <span>{{ $event->areaName }}</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <time
                                    datetime="{{ $event->occurredAt->toDateString() }}"
                                    title="{{ $event->occurredAt->format('M j, Y') }}"
                                    class="text-xs sm:text-sm text-ink-subtle shrink-0 pl-2 pt-1 sm:pt-0 tabular-nums"
                                >{{ $event->occurredAt->format('D') }}</time>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach

            @if ($hasMoreWeeks)
                <div class="pt-2 flex justify-center">
                    <button
                        type="button"
                        wire:click="showEarlierWeeks"
                        class="px-5 py-2.5 max-md:min-h-11 rounded-xl text-sm font-medium text-ink-muted hover:text-ink hover:bg-surface-low transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        {{ __('Show earlier weeks') }}
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
