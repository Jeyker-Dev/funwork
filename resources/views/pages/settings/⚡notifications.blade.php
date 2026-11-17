<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Notifications settings')] class extends Component
{
    #[Validate(['boolean'])]
    public bool $digestEnabled = true;

    #[Validate(['required', 'integer', 'min:0', 'max:23'])]
    public int $digestHour = User::DEFAULT_DIGEST_HOUR;

    public function mount(): void
    {
        $user = Auth::user();

        $this->digestEnabled = (bool) $user->digest_enabled;
        $this->digestHour = (int) $user->digest_hour;
    }

    public function updatedDigestEnabled(): void
    {
        $this->save();
    }

    public function save(): void
    {
        $validated = $this->validate();

        Auth::user()->updateDigestSettings(
            digestEnabled: (bool) $validated['digestEnabled'],
            digestHour: (int) $validated['digestHour'],
        );

        Flux::toast(__('Notification settings updated.'));
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Notifications settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Notifications')" :subheading="__('Configure your morning digest and alert preferences')">
        <div class="space-y-6">
            <flux:card class="space-y-6">
                <div>
                    <flux:heading>{{ __('Morning Digest') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Receive a summary of overdue tasks, tasks due today or within 3 days, and unprocessed inbox items across your active projects.') }}
                    </flux:text>
                </div>

                <flux:switch
                    wire:model.live="digestEnabled"
                    :label="__('Enable morning digest')"
                    :description="__('Delivered at your preferred hour in your profile timezone (:timezone).', ['timezone' => auth()->user()->timezone ?? config('app.timezone')])"
                />

                <form wire:submit="save" class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Preferred delivery time') }}</flux:label>
                        <flux:select wire:model="digestHour" :disabled="! $digestEnabled">
                            @for ($hour = 0; $hour < 24; $hour++)
                                <flux:select.option :value="$hour">{{ sprintf('%02d:00', $hour) }}</flux:select.option>
                            @endfor
                        </flux:select>
                        <flux:error name="digestHour" />
                    </flux:field>

                    <div class="flex items-center justify-end">
                        <flux:button type="submit" variant="primary">
                            {{ __('Save preferences') }}
                        </flux:button>
                    </div>
                </form>
            </flux:card>
        </div>
    </x-pages::settings.layout>
</section>
