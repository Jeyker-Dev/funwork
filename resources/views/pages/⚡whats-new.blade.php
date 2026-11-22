<?php

use App\Services\ChangelogService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')]
#[Title("What's new")]
class extends Component
{
    public ?string $previousLastSeenIso = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->previousLastSeenIso = $user->last_seen_changelog_at?->toIso8601String();

        $user->markChangelogSeen();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(ChangelogService $changelog): array
    {
        $previousLastSeen = $this->previousLastSeenIso !== null
            ? now()->parse($this->previousLastSeenIso)
            : null;

        return [
            'releases' => $changelog->releases(auth()->user(), $previousLastSeen),
        ];
    }
};
?>

<div class="w-full max-w-[760px] mx-auto px-4 sm:px-6 py-6 sm:py-10 space-y-10">
    <div>
        <a
            href="{{ route('projects') }}"
            wire:navigate
            class="inline-flex items-center py-1 max-md:min-h-11 text-sm font-medium text-ink-muted hover:text-ink transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded"
        >
            {{ __('Back to projects') }}
        </a>
    </div>

    <header class="space-y-2">
        <h1 class="font-headline font-bold text-2xl sm:text-3xl tracking-tight text-ink">
            {{ __("What's new in FunWork") }}
        </h1>
        <p class="text-sm sm:text-base text-ink-muted">
            {{ __('Updates and improvements to the app.') }}
        </p>
    </header>

    @if ($releases->isEmpty())
        <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient text-center px-6 py-16 space-y-3">
            <div class="size-12 rounded-2xl bg-surface-low flex items-center justify-center mx-auto text-ink-subtle">
                <flux:icon.sparkles class="size-6" />
            </div>

            <div class="space-y-1.5 max-w-md mx-auto">
                <h2 class="font-headline font-bold text-lg text-ink">
                    {{ __('No updates yet') }}
                </h2>
                <p class="text-sm text-ink-muted">
                    {{ __('Product updates and improvements will appear here.') }}
                </p>
            </div>
        </div>
    @else
        <div class="space-y-10">
            @foreach ($releases as $release)
                <section
                    wire:key="changelog-release-{{ $release->date->toDateString() }}"
                    aria-labelledby="release-{{ $release->date->toDateString() }}"
                    class="space-y-4"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2
                            id="release-{{ $release->date->toDateString() }}"
                            class="font-headline font-semibold text-lg text-ink"
                        >
                            {{ $release->formattedDate }}
                        </h2>

                        @if ($release->isNewSinceLastVisit)
                            <span class="inline-flex items-center gap-2 text-sm font-medium text-accent-content">
                                <span class="size-2 rounded-full bg-brand shrink-0" aria-hidden="true"></span>
                                <span>{{ __('New since your last visit') }}</span>
                            </span>
                        @endif
                    </div>

                    <div class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-7 space-y-6">
                        @foreach ($release->entries as $entryIndex => $entry)
                            <article
                                wire:key="changelog-entry-{{ $release->date->toDateString() }}-{{ $entryIndex }}"
                                class="flex flex-col sm:flex-row sm:items-start justify-between gap-2 sm:gap-4"
                            >
                                <div class="space-y-1.5 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $entry->label->pillClasses() }}">
                                            {{ $entry->label->value }}
                                        </span>
                                        <h3 class="font-semibold text-base text-ink">
                                            {{ $entry->title }}
                                        </h3>
                                    </div>

                                    <p class="text-sm text-ink-muted leading-relaxed">
                                        {{ $entry->description }}
                                    </p>
                                </div>

                                @if ($entry->url !== null)
                                    <a
                                        href="{{ $entry->url }}"
                                        wire:navigate
                                        class="inline-flex items-center shrink-0 self-start max-md:min-h-11 text-sm font-medium text-accent-content hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded"
                                    >
                                        {{ $entry->linkLabel }}
                                    </a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
