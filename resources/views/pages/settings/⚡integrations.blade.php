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

<div class="w-full max-w-[1100px] mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-8">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Integrations settings') }}</flux:heading>

    <x-pages::settings.layout>
        @php
            $mcpUrl = rtrim((string) config('app.url'), '/').'/mcp/funwork';
            $mcpClientConfig = json_encode([
                'mcpServers' => [
                    'funwork' => [
                        'type' => 'http',
                        'url' => $mcpUrl,
                        'headers' => [
                            'Authorization' => 'Bearer YOUR_TOKEN',
                        ],
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        @endphp

        @if (session('google_error'))
            <flux:callout variant="danger" icon="x-circle" :heading="session('google_error')" />
        @endif

        @if (session('google_status'))
            <flux:callout variant="success" icon="check-circle" :heading="session('google_status')" />
        @endif

        <livewire:settings.ai-connections />

        <section class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 sm:p-8 flex flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-headline font-bold text-xl text-ink tracking-tight">
                        {{ __('Connect your AI agent') }}
                    </h2>
                    <p class="text-sm text-ink-muted mt-1">
                        {{ __('Let Claude, Antigravity or any MCP client read and update your FunWork projects.') }}
                    </p>
                </div>

                <div class="size-10 rounded-xl bg-brand-tint text-accent-content flex items-center justify-center shrink-0">
                    <flux:icon.cpu-chip class="size-5" />
                </div>
            </div>

            <div
                class="flex flex-col gap-2"
                x-data="{
                    copied: false,
                    async copyUrl() {
                        try {
                            await navigator.clipboard.writeText(@js($mcpUrl));
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2000);
                        } catch (e) {}
                    }
                }"
            >
                <label class="text-sm font-semibold text-ink" for="mcp-server-url">
                    {{ __('Server URL') }}
                </label>

                <div class="relative flex items-center">
                    <input
                        id="mcp-server-url"
                        type="text"
                        readonly
                        value="{{ $mcpUrl }}"
                        class="w-full bg-surface-low text-ink rounded-lg pl-4 pr-24 py-2.5 text-sm font-mono border border-ghost outline-none focus:border-brand-strong focus:ring-2 focus:ring-brand/20"
                    />
                    <button
                        type="button"
                        aria-label="{{ __('Copy URL') }}"
                        @click="copyUrl()"
                        class="absolute right-1.5 px-3 py-1.5 min-h-9 max-md:min-h-11 rounded-md text-ink-muted hover:text-ink hover:bg-surface-high transition-colors inline-flex items-center gap-1.5 text-xs font-medium cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        <flux:icon.document-duplicate x-show="!copied" class="size-4 shrink-0" />
                        <flux:icon.check x-show="copied" x-cloak class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</span>
                    </button>
                </div>
            </div>

            <div
                class="flex flex-col gap-2"
                x-data="{
                    copied: false,
                    async copySnippet() {
                        try {
                            await navigator.clipboard.writeText(@js($mcpClientConfig));
                            this.copied = true;
                            setTimeout(() => this.copied = false, 2000);
                        } catch (e) {}
                    }
                }"
            >
                <span class="text-sm font-semibold text-ink">
                    {{ __('MCP client configuration') }}
                </span>

                <div class="relative bg-zinc-900 dark:bg-zinc-950 text-zinc-100 rounded-xl p-4 sm:p-5 border border-ghost font-mono text-xs leading-relaxed overflow-x-auto">
                    <div class="flex items-center justify-between gap-2 pb-3 mb-3 text-zinc-400 border-b border-white/10">
                        <span class="text-xs font-medium font-sans">{{ __('Configuration snippet') }}</span>
                        <button
                            type="button"
                            aria-label="{{ __('Copy configuration') }}"
                            @click="copySnippet()"
                            class="inline-flex items-center gap-1.5 text-xs text-zinc-200 hover:text-white px-2.5 py-1.5 min-h-8 max-md:min-h-11 rounded-lg bg-zinc-800 hover:bg-zinc-700 transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            <flux:icon.document-duplicate x-show="!copied" class="size-3.5 shrink-0" />
                            <flux:icon.check x-show="copied" x-cloak class="size-3.5 shrink-0 text-emerald-400" />
                            <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</span>
                        </button>
                    </div>
                    <pre class="m-0 font-mono text-xs sm:text-sm text-zinc-200 overflow-x-auto"><code>{{ $mcpClientConfig }}</code></pre>
                </div>
            </div>
        </section>

        <section class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 sm:p-8 flex flex-col gap-6">
            <div>
                <h2 class="font-headline font-bold text-xl text-ink tracking-tight">
                    {{ __('API tokens') }}
                </h2>
                <p class="text-sm text-ink-muted mt-1">
                    {{ __('Manage personal tokens used to authenticate MCP clients and scripts.') }}
                </p>
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

                <flux:button type="submit" variant="primary" class="shrink-0 max-md:min-h-11">
                    {{ __('Create token') }}
                </flux:button>
            </form>

            @if ($this->apiTokens->isNotEmpty())
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('API tokens') }}">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-xs text-ink-muted font-medium">
                                <th scope="col" class="pb-3 pr-4 font-medium">{{ __('Name') }}</th>
                                <th scope="col" class="pb-3 pr-4 font-medium">{{ __('Last used') }}</th>
                                <th scope="col" class="pb-3 pr-4 font-medium">{{ __('Created') }}</th>
                                <th scope="col" class="pb-3 text-right font-medium"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="text-ink">
                            @foreach ($this->apiTokens as $token)
                                <tr wire:key="api-token-{{ $token->id }}" class="hover:bg-surface-low/60 transition-colors">
                                    <td class="py-3.5 pr-4 font-medium text-ink">
                                        <div class="flex items-center gap-2.5">
                                            <div class="size-7 rounded-lg bg-surface-low flex items-center justify-center text-ink-muted shrink-0">
                                                <flux:icon.key class="size-4" />
                                            </div>
                                            <span>{{ $token->name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 pr-4 text-ink-muted text-sm whitespace-nowrap">
                                        @if ($token->last_used_at)
                                            {{ $token->last_used_at->diffForHumans() }}
                                        @else
                                            {{ __('Never') }}
                                        @endif
                                    </td>
                                    <td class="py-3.5 pr-4 text-ink-muted text-sm whitespace-nowrap">
                                        {{ $token->created_at->format('M j') }}
                                    </td>
                                    <td class="py-3.5 text-right whitespace-nowrap">
                                        <button
                                            type="button"
                                            wire:click="confirmRevokeToken({{ $token->id }})"
                                            class="inline-flex items-center justify-center text-sm text-ink-muted hover:text-danger dark:hover:text-red-400 font-medium transition-colors px-2.5 py-1.5 min-h-9 max-md:min-h-11 rounded-lg hover:bg-surface-low cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                        >
                                            {{ __('Revoke') }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <flux:text variant="subtle">
                    {{ __('No API tokens created yet.') }}
                </flux:text>
            @endif
        </section>

        <section class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-6">
            <div class="flex items-start sm:items-center gap-4">
                <div class="size-12 rounded-xl bg-blue-50 dark:bg-blue-950/50 flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0">
                    <flux:icon.document-text class="size-6" />
                </div>

                <div class="flex flex-col gap-1 min-w-0">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h2 class="font-headline font-bold text-xl text-ink tracking-tight">
                            {{ __('Google Docs') }}
                        </h2>

                        @if ($this->googleAccount)
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-surface-low text-xs text-ink">
                                <span class="size-2 rounded-full bg-emerald-500 shrink-0" aria-hidden="true"></span>
                                <span class="truncate">{{ __('Connected as') }} {{ $this->googleAccount->email }}</span>
                            </div>
                        @endif
                    </div>

                    <p class="text-sm text-ink-muted">
                        {{ __('Notebooks can sync to Google Docs.') }}
                    </p>
                </div>
            </div>

            <div class="shrink-0">
                @if ($this->googleAccount)
                    <flux:modal.trigger name="disconnect-google">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center px-4 py-2 min-h-11 sm:min-h-9 rounded-lg text-sm font-medium text-ink-muted hover:text-ink bg-surface-low hover:bg-surface-high transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            {{ __('Disconnect') }}
                        </button>
                    </flux:modal.trigger>

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
                    <flux:button :href="route('google.redirect')" icon="link" size="sm" class="max-md:min-h-11">
                        {{ __('Connect Google account') }}
                    </flux:button>
                @endif
            </div>
        </section>

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
    </x-pages::settings.layout>
</div>
