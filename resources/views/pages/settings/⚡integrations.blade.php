<?php

use App\Actions\Auth\CreateApiTokenAction;
use App\Models\ApiToken;
use App\Models\GoogleAccount;
use App\Services\Google\GoogleDriveClient;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Integrations settings')] class extends Component
{
    #[Validate('required|string|max:100')]
    public string $tokenName = '';

    #[Locked]
    public ?string $plainTextToken = null;

    #[Locked]
    public ?int $revokingTokenId = null;

    #[Computed]
    public function googleAccount(): ?GoogleAccount
    {
        return GoogleAccount::where('user_id', auth()->id())->first();
    }

    /**
     * @return Collection<int, ApiToken>
     */
    #[Computed]
    public function apiTokens(): Collection
    {
        return ApiToken::where('user_id', auth()->id())
            ->latest()
            ->get();
    }

    public function createToken(CreateApiTokenAction $createApiToken): void
    {
        $this->validateOnly('tokenName');

        $created = $createApiToken(auth()->user(), trim($this->tokenName));

        $this->plainTextToken = $created->plainTextToken;
        $this->reset('tokenName');
        unset($this->apiTokens);

        Flux::modal('api-token-created')->show();
        Flux::toast(__('API token created.'));
    }

    public function closeCreatedTokenModal(): void
    {
        $this->plainTextToken = null;
        Flux::modal('api-token-created')->close();
    }

    public function confirmRevokeToken(int $tokenId): void
    {
        $token = ApiToken::where('user_id', auth()->id())->findOrFail($tokenId);
        $this->revokingTokenId = $token->id;

        Flux::modal('revoke-api-token')->show();
    }

    public function revokeToken(): void
    {
        if ($this->revokingTokenId !== null) {
            $token = ApiToken::where('user_id', auth()->id())->findOrFail($this->revokingTokenId);
            $token->delete();
            $this->revokingTokenId = null;
            unset($this->apiTokens);
        }

        Flux::modal('revoke-api-token')->close();
        Flux::toast(__('API token revoked.'));
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

            <flux:card class="space-y-6">
                <div>
                    <flux:heading>{{ __('API Tokens (MCP)') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Generate personal API tokens to connect coding agents such as Antigravity to the FunWork MCP server.') }}
                    </flux:text>
                </div>

                <form wire:submit="createToken" class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <div class="flex-1">
                        <flux:field>
                            <flux:label class="sr-only">{{ __('Token name') }}</flux:label>
                            <flux:input
                                wire:model="tokenName"
                                :placeholder="__('e.g. Antigravity Laptop')"
                            />
                            <flux:error name="tokenName" />
                        </flux:field>
                    </div>

                    <flux:button type="submit" variant="primary" icon="key">
                        {{ __('Create token') }}
                    </flux:button>
                </form>

                @if ($this->apiTokens->isNotEmpty())
                    <div class="divide-y divide-zinc-200 dark:divide-white/10">
                        @foreach ($this->apiTokens as $token)
                            <div wire:key="api-token-{{ $token->id }}" class="flex items-center justify-between gap-4 py-3">
                                <div>
                                    <flux:text class="font-medium">{{ $token->name }}</flux:text>
                                    <flux:text variant="subtle" class="text-xs">
                                        {{ __('Created') }} {{ $token->created_at->diffForHumans() }}
                                        ·
                                        @if ($token->last_used_at)
                                            {{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}
                                        @else
                                            {{ __('Never used') }}
                                        @endif
                                    </flux:text>
                                </div>

                                <flux:button
                                    variant="danger"
                                    size="sm"
                                    wire:click="confirmRevokeToken({{ $token->id }})"
                                >
                                    {{ __('Revoke') }}
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text variant="subtle">
                        {{ __('No API tokens created yet.') }}
                    </flux:text>
                @endif
            </flux:card>

            <flux:modal name="api-token-created" class="min-w-[24rem]">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('API Token Created') }}</flux:heading>
                        <flux:text class="mt-2">
                            {{ __('Copy your new API token now. For security reasons, it will not be shown again.') }}
                        </flux:text>
                    </div>

                    @if ($plainTextToken)
                        <flux:input readonly :value="$plainTextToken" copyable />
                    @endif

                    <div class="flex justify-end">
                        <flux:button variant="primary" wire:click="closeCreatedTokenModal">
                            {{ __('Done') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>

            <flux:modal name="revoke-api-token" class="min-w-[22rem]">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Revoke API token') }}</flux:heading>
                        <flux:text class="mt-2">
                            {{ __('Any agent using this token will immediately lose access to FunWork.') }}
                        </flux:text>
                    </div>

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>

                        <flux:button variant="danger" wire:click="revokeToken">
                            {{ __('Revoke') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        </div>
    </x-pages::settings.layout>
</section>
