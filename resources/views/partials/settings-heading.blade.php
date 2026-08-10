<div class="relative mb-6 w-full">
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Settings') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">{{ __('Manage your profile and account settings') }}</flux:subheading>
        </div>
        
        <flux:button variant="subtle" icon="arrow-left" :href="route('projects')" wire:navigate class="hidden sm:flex">
            {{ __('Back to Projects') }}
        </flux:button>
        <flux:button variant="subtle" icon="arrow-left" :href="route('projects')" wire:navigate class="sm:hidden" />
    </div>
    
    <flux:separator variant="subtle" />
</div>
