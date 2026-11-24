<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<div class="w-full max-w-[1100px] mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-8">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-pages::settings.layout>
        <section class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 sm:p-8 flex flex-col gap-6">
            <div>
                <h2 class="font-headline font-bold text-xl text-ink tracking-tight">
                    {{ __('Appearance') }}
                </h2>
                <p class="text-sm text-ink-muted mt-1">
                    {{ __('Update the appearance settings for your account.') }}
                </p>
            </div>

            <div class="max-w-md">
                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                    <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                    <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                    <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
                </flux:radio.group>
            </div>
        </section>
    </x-pages::settings.layout>
</div>
