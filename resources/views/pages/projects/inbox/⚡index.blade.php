<?php

use App\Actions\Projects\GetProjectInboxDataAction;
use App\Livewire\Forms\InboxItemForm;
use App\Models\InboxItem;
use App\Models\Project;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')]
#[Title('Inbox')]
class extends Component {
    public ?Project $project = null;

    public InboxItemForm $form;

    public bool $isProcessing = false;

    /**
     * @var array<int, int>
     */
    public array $processQueue = [];

    public int $processIndex = 0;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->form->projectId = $project->id;
        $this->form->userId = auth()->id();
    }

    public function addItem(): void
    {
        $this->authorize('create', [InboxItem::class, $this->project]);

        $this->form->store();

        $this->dispatch('inbox-item-captured');
    }

    public function openProcessMode(GetProjectInboxDataAction $getProjectInboxData): void
    {
        $inbox = $getProjectInboxData($this->project, auth()->user());

        if ($inbox->isEmpty()) {
            return;
        }

        $this->processQueue = $inbox->oldestFirstItems->pluck('id')->all();
        $this->processIndex = 0;
        $this->isProcessing = true;
    }

    public function closeProcessMode(): void
    {
        $this->isProcessing = false;
        $this->processQueue = [];
        $this->processIndex = 0;
    }

    public function skipCurrentItem(): void
    {
        if (! $this->isProcessing) {
            return;
        }

        $this->advanceProcessQueue();
    }

    #[On('item-deleted')]
    #[On('item-converted')]
    public function handleItemProcessed(?int $id = null): void
    {
        if ($this->isProcessing) {
            $currentId = $this->processQueue[$this->processIndex] ?? null;

            if ($id === null || $id === $currentId) {
                $this->advanceProcessQueue();
            }
        }

        $remainingCount = auth()->user()
            ->inboxItems()
            ->where('project_id', $this->project->id)
            ->count();

        if ($remainingCount === 0) {
            $this->closeProcessMode();
            $this->dispatch('focus-capture-bar');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function with(GetProjectInboxDataAction $getProjectInboxData): array
    {
        $inbox = $getProjectInboxData($this->project, auth()->user());
        $currentProcessItem = $this->resolveCurrentProcessItem($inbox->oldestFirstItems);

        return [
            'inbox' => $inbox,
            'currentProcessItem' => $currentProcessItem,
            'processPosition' => $this->processIndex + 1,
            'processTotal' => count($this->processQueue),
        ];
    }

    private function advanceProcessQueue(): void
    {
        $this->processIndex++;

        if ($this->processIndex >= count($this->processQueue)) {
            $this->closeProcessMode();
        }
    }

    /**
     * @param  Collection<int, InboxItem>  $oldestFirstItems
     */
    private function resolveCurrentProcessItem(Collection $oldestFirstItems): ?InboxItem
    {
        if (! $this->isProcessing) {
            return null;
        }

        while ($this->processIndex < count($this->processQueue)) {
            $candidate = $oldestFirstItems->firstWhere('id', $this->processQueue[$this->processIndex]);

            if ($candidate !== null) {
                return $candidate;
            }

            $this->processIndex++;
        }

        $this->closeProcessMode();

        return null;
    }
};
?>

<div class="max-w-4xl w-full mx-auto py-2 sm:py-4">
    <div class="space-y-6 sm:space-y-8">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-baseline gap-3.5 min-w-0">
                <h1 class="font-headline font-bold text-3xl tracking-tight text-ink">
                    Inbox
                </h1>

                <span class="font-body text-sm font-medium text-ink-muted bg-surface-low px-2.5 py-0.5 rounded-full tabular-nums">
                    {{ $inbox->totalCount }} to process
                </span>
            </div>

            @if(! $inbox->isEmpty())
                <flux:button variant="primary" class="max-md:min-h-11 shrink-0" wire:click="openProcessMode">
                    Process inbox
                </flux:button>
            @endif
        </header>

        <x-projects.inbox.capture-bar :is-empty="$inbox->isEmpty()" />

        @if($inbox->isEmpty())
            <x-projects.inbox.empty-state />
        @else
            <section aria-label="Inbox items" class="w-full bg-surface-card rounded-2xl border border-ghost shadow-ambient p-4 sm:p-7 flex flex-col gap-6 sm:gap-7">
                @foreach($inbox->groups as $groupLabel => $groupItems)
                    <section class="flex flex-col gap-2" aria-label="{{ $groupLabel }}">
                        <h2 class="font-body text-xs font-medium text-ink-muted px-3">
                            {{ $groupLabel }}
                        </h2>

                        <div class="flex flex-col">
                            @foreach($groupItems as $item)
                                <x-projects.inbox.item-row :item="$item" />
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </section>
        @endif
    </div>

    @if($isProcessing && $currentProcessItem)
        <x-projects.inbox.process-panel
            :item="$currentProcessItem"
            :position="$processPosition"
            :total="$processTotal"
        />
    @endif

    <livewire:projects.inbox.convert-to-task />
    <livewire:projects.inbox.convert-to-notebook />
    <livewire:projects.inbox.delete-item-modal />
</div>
