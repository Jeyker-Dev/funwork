<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
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

    #[Computed]
    public function hasVapidKey(): bool
    {
        return filled(config('webpush.vapid.public_key'));
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

        $this->dispatch('notifications-updated');
        Flux::toast(__('Notification settings updated.'));
    }
};
?>

<div class="w-full max-w-[1100px] mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-8">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Notifications settings') }}</flux:heading>

    <x-pages::settings.layout>
        <section class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 sm:p-8 flex flex-col gap-6">
            <div>
                <h2 class="font-headline font-bold text-xl text-ink tracking-tight">
                    {{ __('Morning Digest') }}
                </h2>
                <p class="text-sm text-ink-muted mt-1">
                    {{ __('Receive a summary of overdue tasks, tasks due today or within 3 days, and unprocessed inbox items across your active projects.') }}
                </p>
            </div>

            <flux:switch
                wire:model.live="digestEnabled"
                :label="__('Enable morning digest')"
                :description="__('Delivered at your preferred hour in your profile timezone (:timezone).', ['timezone' => auth()->user()->timezone ?? config('app.timezone')])"
            />

            <form wire:submit="save" class="space-y-6">
                <div class="max-w-sm">
                    <flux:field>
                        <flux:label>{{ __('Preferred delivery time') }}</flux:label>
                        <flux:select wire:model="digestHour" :disabled="! $digestEnabled">
                            @for ($hour = 0; $hour < 24; $hour++)
                                <flux:select.option :value="$hour">{{ sprintf('%02d:00', $hour) }}</flux:select.option>
                            @endfor
                        </flux:select>
                        <flux:error name="digestHour" />
                    </flux:field>
                </div>

                <div class="flex items-center justify-end gap-4 pt-2">
                    <x-action-message on="notifications-updated" class="text-xs font-medium text-ink-muted">
                        {{ __('Saved') }}
                    </x-action-message>

                    <flux:button type="submit" variant="primary" class="max-md:min-h-11">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </form>
        </section>

        @if ($this->hasVapidKey)
            <script>
                window.funworkBrowserPushSettings = window.funworkBrowserPushSettings || function (config) {
                    return {
                        state: 'loading',
                        busy: false,
                        errorMessage: '',

                        async init() {
                            if (! ('serviceWorker' in navigator) || ! ('PushManager' in window) || ! ('Notification' in window)) {
                                this.state = 'unsupported';
                                return;
                            }

                            if (Notification.permission === 'denied') {
                                this.state = 'denied';
                                return;
                            }

                            try {
                                const registration = await navigator.serviceWorker.register('/sw.js');
                                const existing = await registration.pushManager.getSubscription();
                                this.state = existing ? 'subscribed' : 'unsubscribed';
                            } catch (error) {
                                this.state = 'unsubscribed';
                                this.errorMessage = error?.message || 'Unable to inspect browser push status.';
                            }
                        },

                        urlBase64ToUint8Array(base64String) {
                            const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
                            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                            const rawData = window.atob(base64);
                            const output = new Uint8Array(rawData.length);

                            for (let index = 0; index < rawData.length; index += 1) {
                                output[index] = rawData.charCodeAt(index);
                            }

                            return output;
                        },

                        async enablePush() {
                            if (this.busy) {
                                return;
                            }

                            this.busy = true;
                            this.errorMessage = '';

                            try {
                                const permission = await Notification.requestPermission();

                                if (permission === 'denied') {
                                    this.state = 'denied';
                                    return;
                                }

                                if (permission !== 'granted') {
                                    this.state = 'unsubscribed';
                                    return;
                                }

                                const registration = await navigator.serviceWorker.register('/sw.js');
                                await navigator.serviceWorker.ready;

                                const existing = await registration.pushManager.getSubscription();
                                const subscription = existing || await registration.pushManager.subscribe({
                                    userVisibleOnly: true,
                                    applicationServerKey: this.urlBase64ToUint8Array(config.publicKey),
                                });

                                const json = subscription.toJSON();
                                const encoding = Array.isArray(PushManager.supportedContentEncodings) && PushManager.supportedContentEncodings.length > 0
                                    ? PushManager.supportedContentEncodings[0]
                                    : 'aes128gcm';

                                const response = await fetch(config.storeUrl, {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': config.csrfToken,
                                    },
                                    body: JSON.stringify({
                                        endpoint: json.endpoint,
                                        keys: json.keys,
                                        contentEncoding: encoding,
                                    }),
                                });

                                if (! response.ok) {
                                    throw new Error('Failed to save browser push subscription.');
                                }

                                this.state = 'subscribed';
                            } catch (error) {
                                this.errorMessage = error?.message || 'Unable to enable browser notifications.';
                            } finally {
                                this.busy = false;
                            }
                        },

                        async disablePush() {
                            if (this.busy) {
                                return;
                            }

                            this.busy = true;
                            this.errorMessage = '';

                            try {
                                const registration = await navigator.serviceWorker.getRegistration('/sw.js')
                                    || await navigator.serviceWorker.ready;
                                const subscription = registration ? await registration.pushManager.getSubscription() : null;

                                if (subscription) {
                                    const response = await fetch(config.destroyUrl, {
                                        method: 'DELETE',
                                        credentials: 'same-origin',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': config.csrfToken,
                                        },
                                        body: JSON.stringify({
                                            endpoint: subscription.endpoint,
                                        }),
                                    });

                                    if (! response.ok) {
                                        throw new Error('Failed to remove browser push subscription.');
                                    }

                                    await subscription.unsubscribe();
                                }

                                this.state = 'unsubscribed';
                            } catch (error) {
                                this.errorMessage = error?.message || 'Unable to disable browser notifications.';
                            } finally {
                                this.busy = false;
                            }
                        },
                    };
                };

                window.funworkBrowserPushConfig = @js([
                    'publicKey' => (string) config('webpush.vapid.public_key', ''),
                    'storeUrl' => route('notifications.push-subscription.store'),
                    'destroyUrl' => route('notifications.push-subscription.destroy'),
                    'csrfToken' => csrf_token(),
                ]);
            </script>

            <section
                wire:ignore
                class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 sm:p-8 flex flex-col gap-4"
                x-data="funworkBrowserPushSettings(window.funworkBrowserPushConfig)"
                x-cloak
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="font-headline font-bold text-xl text-ink tracking-tight">
                            {{ __('Browser notifications') }}
                        </h2>
                        <p class="text-sm text-ink-muted mt-1">
                            {{ __('Receive Web Push alerts in this browser even when FunWork is closed.') }}
                        </p>
                    </div>

                    <template x-if="state === 'subscribed'">
                        <flux:badge color="green" size="sm">{{ __('Active on this device') }}</flux:badge>
                    </template>
                    <template x-if="state === 'unsubscribed'">
                        <flux:badge color="zinc" size="sm">{{ __('Disabled') }}</flux:badge>
                    </template>
                    <template x-if="state === 'denied'">
                        <flux:badge color="red" size="sm">{{ __('Blocked') }}</flux:badge>
                    </template>
                    <template x-if="state === 'unsupported'">
                        <flux:badge color="zinc" size="sm">{{ __('Unsupported') }}</flux:badge>
                    </template>
                </div>

                <template x-if="errorMessage">
                    <flux:callout variant="danger" icon="x-circle">
                        <flux:callout.heading x-text="errorMessage" />
                    </flux:callout>
                </template>

                <template x-if="state === 'unsupported'">
                    <div class="space-y-3">
                        <flux:callout
                            variant="warning"
                            icon="exclamation-triangle"
                            :heading="__('Browser push is not supported here')"
                            :text="__('This browser or context does not support Service Workers or the Push API.')"
                        />
                        <flux:button variant="primary" icon="bell" disabled class="max-md:min-h-11">
                            {{ __('Enable push on this browser') }}
                        </flux:button>
                    </div>
                </template>

                <template x-if="state === 'denied'">
                    <div class="space-y-3">
                        <flux:callout
                            variant="warning"
                            icon="exclamation-triangle"
                            :heading="__('Notifications are blocked in your browser')"
                            :text="__('Open your browser site permissions for this page, set Notifications to Allow, and reload to enable Web Push.')"
                        />
                        <flux:button variant="primary" icon="bell" disabled class="max-md:min-h-11">
                            {{ __('Enable push on this browser') }}
                        </flux:button>
                    </div>
                </template>

                <template x-if="state === 'unsubscribed'">
                    <div>
                        <flux:button
                            type="button"
                            variant="primary"
                            icon="bell"
                            x-on:click="enablePush()"
                            x-bind:disabled="busy"
                            class="max-md:min-h-11"
                        >
                            {{ __('Enable push on this browser') }}
                        </flux:button>
                    </div>
                </template>

                <template x-if="state === 'subscribed'">
                    <div>
                        <flux:button
                            type="button"
                            variant="danger"
                            icon="bell-slash"
                            x-on:click="disablePush()"
                            x-bind:disabled="busy"
                            class="max-md:min-h-11"
                        >
                            {{ __('Disable') }}
                        </flux:button>
                    </div>
                </template>

                <flux:text variant="subtle" class="text-xs">
                    {{ __('On iOS, Web Push only works when FunWork is installed to the Home Screen.') }}
                </flux:text>
            </section>
        @endif
    </x-pages::settings.layout>
</div>
