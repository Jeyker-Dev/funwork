<?php

use App\Models\Decision;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')]
#[Title('Decision Details')]
class extends Component
{
    public Project $project;

    public Decision $decision;

    public function mount(Project $project, Decision $decision): void
    {
        $this->authorize('view', $project);
        $this->authorize('view', $decision);

        $this->project = $project;
        $this->decision = $decision->load(['supersededBy', 'creator']);
    }
};
?>

<div class="max-w-4xl mx-auto w-full py-6 space-y-6">
    <div>
        <a
            href="{{ route('project.decisions', ['project' => $project]) }}"
            class="inline-flex items-center gap-2 text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 transition"
            wire:navigate
        >
            <flux:icon.arrow-left class="size-4" stroke-width="2.5" />
            {{ __('Back to Decisions') }}
        </a>
    </div>

    <flux:card class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="font-mono text-sm font-semibold text-zinc-500 dark:text-zinc-400">
                    {{ $decision->code }}
                </span>

                <flux:badge :color="$decision->status->fluxColor()">
                    {{ $decision->status->label() }}
                </flux:badge>
            </div>

            @if ($decision->decided_at)
                <div class="flex items-center gap-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                    <flux:icon.calendar-days class="size-4" />
                    <span>{{ __('Decided') }} {{ $decision->decided_at->format('M d, Y') }}</span>
                </div>
            @endif
        </div>

        @if ($decision->supersededBy)
            <flux:callout variant="warning" icon="arrow-uturn-right">
                <flux:callout.heading>{{ __('Superseded') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('This decision was superseded by') }}
                    <a
                        href="{{ route('project.decisions.show', ['project' => $project, 'decision' => $decision->supersededBy]) }}"
                        class="font-semibold underline hover:no-underline"
                        wire:navigate
                    >
                        {{ $decision->supersededBy->code }}: {{ $decision->supersededBy->title }}
                    </a>.
                </flux:callout.text>
            </flux:callout>
        @endif

        <flux:heading size="xl" level="1">{{ $decision->title }}</flux:heading>

        <div class="space-y-6 pt-2 border-t border-zinc-200 dark:border-zinc-800">
            <section class="space-y-2">
                <flux:heading size="lg" level="2">{{ __('Context') }}</flux:heading>
                <div class="markdown-content">
                    {!! $decision->context_html !!}
                </div>
            </section>

            <section class="space-y-2">
                <flux:heading size="lg" level="2">{{ __('Decision') }}</flux:heading>
                <div class="markdown-content">
                    {!! $decision->decision_html !!}
                </div>
            </section>

            <section class="space-y-2">
                <flux:heading size="lg" level="2">{{ __('Consequences') }}</flux:heading>
                <div class="markdown-content">
                    {!! $decision->consequences_html !!}
                </div>
            </section>
        </div>
    </flux:card>
</div>
