<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-6">
    <div class="max-w-md">
        <h2 class="font-headline font-semibold text-lg text-ink">
            {{ __('Delete account') }}
        </h2>
        <p class="text-sm text-ink-muted mt-1 leading-relaxed">
            {{ __('Permanently delete your account and all of its projects. This action cannot be undone.') }}
        </p>
    </div>

    <div class="shrink-0">
        <flux:modal.trigger name="confirm-user-deletion">
            <button
                type="button"
                data-test="delete-user-button"
                class="inline-flex items-center justify-center px-5 py-2.5 min-h-11 sm:min-h-10 rounded-lg font-semibold text-sm text-danger dark:text-red-400 bg-surface-card border border-ghost hover:bg-danger-tint dark:hover:bg-red-950/30 transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger"
            >
                {{ __('Delete account') }}
            </button>
        </flux:modal.trigger>
    </div>

    <livewire:pages::settings.delete-user-modal />
</section>
