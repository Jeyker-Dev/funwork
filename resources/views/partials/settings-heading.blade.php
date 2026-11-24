<div class="space-y-4">
    <div>
        <a
            href="{{ route('projects') }}"
            wire:navigate
            class="inline-flex items-center py-1 max-md:min-h-11 text-sm font-medium text-ink-muted hover:text-ink transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded"
        >
            {{ __('Back to projects') }}
        </a>
    </div>

    <header class="space-y-1.5">
        <h1 class="font-headline font-bold text-2xl sm:text-3xl tracking-tight text-ink">
            {{ __('Settings') }}
        </h1>
        <p class="text-sm sm:text-base text-ink-muted">
            {{ __('Manage your personal credentials, workspace preferences, and profile defaults.') }}
        </p>
    </header>
</div>
