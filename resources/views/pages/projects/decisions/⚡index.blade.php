<?php

use App\Enums\DecisionStatus;
use App\Models\Decision;
use App\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')]
#[Title('Decisions')]
class extends Component
{
    public Project $project;

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    public function mount(Project $project): void
    {
        $this->authorize('viewAny', [Decision::class, $project]);

        $this->project = $project;
    }

    public function with(): array
    {
        $status = DecisionStatus::tryFrom($this->statusFilter);

        $decisions = $this->project->decisions()
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->orderByDesc('number')
            ->get();

        return [
            'decisions' => $decisions,
            'statuses' => DecisionStatus::cases(),
        ];
    }
};
?>

<div class="max-w-5xl mx-auto w-full py-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Decisions') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Architecture Decision Records (ADRs) for this project.') }}
            </flux:text>
        </div>

        <div class="w-48">
            <flux:select wire:model.live="statusFilter" aria-label="Filter by status">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if ($decisions->isEmpty())
        <flux:card class="text-center py-12">
            <flux:icon.scale class="size-12 mx-auto text-zinc-400" />
            <flux:heading size="lg" class="mt-4">{{ __('No decisions found') }}</flux:heading>
            <flux:text class="mt-1">
                {{ $statusFilter !== '' ? __('No decisions match the selected status.') : __('Record your first architecture decision for this project.') }}
            </flux:text>
        </flux:card>
    @else
        <flux:card class="p-0 overflow-hidden">
            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($decisions as $decision)
                    <a
                        wire:key="decision-{{ $decision->id }}"
                        href="{{ route('project.decisions.show', ['project' => $project, 'decision' => $decision]) }}"
                        wire:navigate
                        class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="font-mono text-xs font-semibold text-zinc-500 dark:text-zinc-400 shrink-0">
                                {{ $decision->code }}
                            </span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $decision->title }}
                            </span>
                        </div>

                        <div class="flex items-center gap-4 shrink-0">
                            <flux:badge size="sm" :color="$decision->status->fluxColor()">
                                {{ $decision->status->label() }}
                            </flux:badge>

                            <span class="text-xs text-zinc-500 dark:text-zinc-400 w-24 text-right">
                                {{ $decision->decided_at?->format('M d, Y') ?? '—' }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
