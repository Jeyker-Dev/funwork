<?php

use App\Models\GoogleAccount;
use App\Services\Google\GoogleDriveClient;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Integrations settings')] class extends Component
{
    #[Computed]
    public function googleAccount(): ?GoogleAccount
    {
        return GoogleAccount::where('user_id', auth()->id())->first();
    }

    public function disconnectGoogle(GoogleDriveClient $drive): void
    {
        $account = $this->googleAccount;

        if ($account) {
            $drive->revoke($account);
            $account->delete();
        }

        unset($this->googleAccount);

        Flux::modal('disconnect-google')->close();
        Flux::toast(__('Google account disconnected.'));
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Integrations settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Integrations')" :subheading="__('Connect external services to your account')">
        <div class="space-y-4">
            @if (session('google_error'))
                <flux:callout variant="danger" icon="x-circle" :heading="session('google_error')" />
            @endif

            @if (session('google_status'))
                <flux:callout variant="success" icon="check-circle" :heading="session('google_status')" />
            @endif

            <flux:card class="space-y-4">
                <div>
                    <flux:heading>{{ __('Google Docs') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Publish each project pack to a Google Doc in your Drive, ready to sync in NotebookLM. FunWork only reaches the files it creates.') }}
                    </flux:text>
                </div>

                @if ($this->googleAccount)
                    <div class="flex items-center justify-between gap-4">
                        <flux:text>{{ __('Connected as') }} <strong>{{ $this->googleAccount->email }}</strong></flux:text>

                        <flux:modal.trigger name="disconnect-google">
                            <flux:button variant="danger" size="sm">{{ __('Disconnect') }}</flux:button>
                        </flux:modal.trigger>
                    </div>

                    <flux:modal name="disconnect-google" class="min-w-[22rem]">
                        <div class="space-y-6">
                            <div>
                                <flux:heading size="lg">{{ __('Disconnect Google account') }}</flux:heading>
                                <flux:text class="mt-2">
                                    {{ __('FunWork will lose access to the Google Docs it created. The Docs stay in your Drive, but they will not be synced until you connect Google again.') }}
                                </flux:text>
                            </div>

                            <div class="flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>

                                <flux:button variant="danger" wire:click="disconnectGoogle">{{ __('Disconnect') }}</flux:button>
                            </div>
                        </div>
                    </flux:modal>
                @else
                    <flux:button :href="route('google.redirect')" icon="link" size="sm">
                        {{ __('Connect Google account') }}
                    </flux:button>
                @endif
            </flux:card>
        </div>
    </x-pages::settings.layout>
</section>
