<?php

use App\Actions\Projects\ResolveQuickCaptureProjectAction;
use App\Http\Middleware\RememberLastVisitedProject;
use App\Livewire\Forms\InboxItemForm;
use App\Models\Project;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public InboxItemForm $form;

    public ?string $destinationProjectName = null;

    public function mount(
        ResolveQuickCaptureProjectAction $resolveDestinationProject,
        ?Project $project = null,
    ): void {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        if ($project !== null) {
            $this->authorize('view', $project);
        }

        $destinationProject = $resolveDestinationProject(
            user: $user,
            routeProject: $project,
            lastProjectId: session(RememberLastVisitedProject::SESSION_KEY),
        );

        $this->form->projectId = $destinationProject?->id;
        $this->form->userId = $user->id;
        $this->destinationProjectName = $destinationProject?->name;
    }

    public function save(): void
    {
        if ($this->form->projectId === null) {
            return;
        }

        $this->form->store();

        Flux::modal('global-quick-capture')->close();

        $this->dispatch('quick-capture-saved');
    }
};
?>

@php
    $hasDestinationProject = $this->form->projectId !== null && $destinationProjectName !== null;
@endphp

<div
    x-data="{
        isTypingTarget(target) {
            if (! target) {
                return false;
            }

            return ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)
                || target.isContentEditable
                || Boolean(target.closest?.('[contenteditable]:not([contenteditable=false])'));
        },
        hasOpenDialog() {
            return document.querySelector('dialog[open], [role=dialog][aria-modal=true]') !== null;
        },
        handleShortcut(event) {
            if (event.repeat || event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            if (this.hasOpenDialog() || this.isTypingTarget(event.target)) {
                return;
            }

            if (event.key.toLowerCase() === 'c') {
                event.preventDefault();
                $flux.modal('global-quick-capture').show();
                $nextTick(() => $refs.captureInput?.focus());
            }
        }
    }"
    x-on:keydown.window="handleShortcut($event)"
    x-on:modal-show.window="if ($event.detail?.name === 'global-quick-capture') { $nextTick(() => $refs.captureInput?.focus()); }"
>
    <flux:modal
        name="global-quick-capture"
        class="w-[calc(100vw-2rem)] max-w-lg bg-surface-card dark:bg-surface-card border border-ghost rounded-2xl shadow-ambient overscroll-contain"
    >
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg" class="text-ink">Quick Capture</flux:heading>

                @if ($hasDestinationProject)
                    <p class="mt-1 text-sm text-ink-muted">
                        Saving to <span class="font-medium text-ink">{{ $destinationProjectName }}</span> inbox
                    </p>
                @else
                    <p class="mt-1 text-sm text-ink-muted">
                        No projects yet. Create a project first to capture ideas.
                    </p>
                @endif
            </div>

            <div class="space-y-1.5">
                <label for="global-quick-capture-input" class="block text-xs font-medium text-ink-muted">
                    Idea
                </label>

                <div class="w-full bg-surface-card dark:bg-surface-high/60 rounded-2xl border border-ghost shadow-ambient p-2 sm:p-2.5 focus-within:border-brand-strong focus-within:ring-4 focus-within:ring-brand/15 transition">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <flux:icon name="plus" class="size-5 text-ink-subtle shrink-0 ml-2" aria-hidden="true" />

                        <div class="flex-1 min-w-0">
                            <input
                                id="global-quick-capture-input"
                                type="text"
                                wire:model="form.content"
                                x-ref="captureInput"
                                x-on:keydown.enter="if ($event.isComposing || $event.keyCode === 229) { $event.preventDefault(); }"
                                autofocus
                                placeholder="{{ $hasDestinationProject ? 'Capture an idea and press Enter' : 'Create a project first to capture ideas' }}"
                                @disabled(! $hasDestinationProject)
                                class="w-full bg-transparent border-none focus:outline-none focus:ring-0 text-sm sm:text-base px-1 py-2 text-ink placeholder:text-ink-subtle disabled:cursor-not-allowed disabled:opacity-60"
                            />
                        </div>

                        <kbd aria-hidden="true" class="hidden sm:inline-flex items-center justify-center px-2 py-1 rounded-md bg-surface-low dark:bg-surface-high border border-ghost text-ink-muted text-xs font-medium shrink-0 select-none mr-1">
                            ↵ Enter
                        </kbd>
                    </div>
                </div>

                @error('form.content')
                    <p role="alert" class="px-1 pt-1 text-xs text-danger dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                <p class="hidden sm:flex items-center gap-1.5 text-xs text-ink-muted">
                    <span>Shortcut</span>
                    <kbd aria-hidden="true" class="inline-flex items-center justify-center px-2 py-0.5 rounded-md bg-surface-low dark:bg-surface-high border border-ghost text-ink-muted text-xs font-medium select-none">C</kbd>
                    <span aria-hidden="true">·</span>
                    <kbd aria-hidden="true" class="inline-flex items-center justify-center px-2 py-0.5 rounded-md bg-surface-low dark:bg-surface-high border border-ghost text-ink-muted text-xs font-medium select-none">Esc</kbd>
                    <span>to close</span>
                </p>

                <div class="flex items-center gap-2 ml-auto">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="max-md:min-h-11">Cancel</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="primary" :disabled="! $hasDestinationProject" class="max-md:min-h-11">
                        Capture
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
