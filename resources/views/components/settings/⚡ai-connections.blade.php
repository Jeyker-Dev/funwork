<?php

use App\Actions\AiConnections\CreateAiConnectionAction;
use App\Actions\AiConnections\DeleteAiConnectionAction;
use App\Actions\AiConnections\MakeDefaultAiConnectionAction;
use App\Actions\AiConnections\UpdateAiConnectionAction;
use App\Data\AiConnections\AiConnectionData;
use App\Enums\AiProvider;
use App\Exceptions\AiConnections\EmptyAiApiKeyException;
use App\Exceptions\AiConnections\EmptyAiModelException;
use App\Exceptions\AiConnections\InvalidAiBaseUrlException;
use App\Exceptions\BusinessRuleException;
use App\Models\AiConnection;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    private const string CUSTOM_MODEL_OPTION = '__custom__';

    #[Locked]
    public ?int $editingConnectionId = null;

    #[Locked]
    public ?int $removingConnectionId = null;

    public string $provider = '';

    public string $label = '';

    public string $baseUrl = '';

    public string $selectedModel = '';

    public string $model = '';

    public string $apiKey = '';

    public bool $allowPaidModels = false;

    public function mount(): void
    {
        $this->resetForm();
    }

    /**
     * @return Collection<int, AiConnection>
     */
    #[Computed]
    public function connections(): Collection
    {
        return AiConnection::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('is_default')
            ->latest('id')
            ->get();
    }

    #[Computed]
    public function editingConnection(): ?AiConnection
    {
        if ($this->editingConnectionId === null) {
            return null;
        }

        return $this->connections->firstWhere('id', $this->editingConnectionId);
    }

    #[Computed]
    public function removingConnection(): ?AiConnection
    {
        if ($this->removingConnectionId === null) {
            return null;
        }

        return $this->connections->firstWhere('id', $this->removingConnectionId);
    }

    public function updatedProvider(string $value): void
    {
        $provider = AiProvider::tryFrom($value) ?? AiProvider::OpenRouter;

        if (! $provider->isCustom()) {
            $this->baseUrl = '';
        }

        if (! $provider->isOpenRouter()) {
            $this->allowPaidModels = false;
        }

        $this->syncModelSelectionFor($provider);
    }

    public function updatedSelectedModel(string $value): void
    {
        if ($value === self::CUSTOM_MODEL_OPTION) {
            $this->model = '';

            return;
        }

        $this->model = $value;
    }

    public function updatedAllowPaidModels(): void
    {
        $provider = $this->currentProvider();
        $available = $this->availableModelsFor($provider);

        if ($this->selectedModel !== self::CUSTOM_MODEL_OPTION && ! in_array($this->selectedModel, $available, true)) {
            $this->syncModelSelectionFor($provider);
        }
    }

    public function edit(int $connectionId): void
    {
        $connection = AiConnection::query()->findOrFail($connectionId);
        $this->authorize('update', $connection);

        $this->editingConnectionId = $connection->id;
        $this->provider = $connection->provider->value;
        $this->label = (string) ($connection->label ?? '');
        $this->baseUrl = (string) ($connection->base_url ?? '');
        $this->allowPaidModels = $connection->provider->isOpenRouter() && $connection->allow_paid_models;
        $this->apiKey = '';

        $available = $this->availableModelsFor($connection->provider);

        if (in_array($connection->model, $available, true)) {
            $this->selectedModel = $connection->model;
            $this->model = $connection->model;
        } else {
            $this->selectedModel = self::CUSTOM_MODEL_OPTION;
            $this->model = $connection->model;
        }

        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function save(
        CreateAiConnectionAction $createConnection,
        UpdateAiConnectionAction $updateConnection,
    ): void {
        // Capture and immediately wipe the secret so it never lingers in public Livewire state or snapshots.
        $apiKey = $this->apiKey;
        $this->apiKey = '';
        $this->resetErrorBag();

        $provider = AiProvider::tryFrom($this->provider);

        if ($provider === null) {
            $this->addError('provider', __('Please select a valid AI provider.'));

            return;
        }

        $model = trim($this->model);

        if ($model === '' && $this->selectedModel !== '' && $this->selectedModel !== self::CUSTOM_MODEL_OPTION) {
            $model = trim($this->selectedModel);
        }

        $input = new AiConnectionData(
            provider: $provider,
            label: $this->label,
            baseUrl: $this->baseUrl,
            model: $model,
            apiKey: $apiKey,
            allowPaidModels: $this->allowPaidModels,
        );

        try {
            if ($this->editingConnectionId === null) {
                $createConnection(Auth::user(), $input);
                $this->resetForm();
                unset($this->connections, $this->editingConnection);

                Flux::toast(__('AI connection saved.'), variant: 'success');

                return;
            }

            $connection = AiConnection::query()->findOrFail($this->editingConnectionId);
            $updateConnection($connection, Auth::user(), $input);
            $this->resetForm();
            unset($this->connections, $this->editingConnection);

            Flux::toast(__('AI connection updated.'), variant: 'success');
        } catch (BusinessRuleException $exception) {
            $this->addError($this->fieldForException($exception), $exception->getMessage());
        }
    }

    public function makeDefault(int $connectionId, MakeDefaultAiConnectionAction $makeDefault): void
    {
        $connection = AiConnection::query()->findOrFail($connectionId);
        $makeDefault($connection, Auth::user());

        unset($this->connections);

        Flux::toast(__('Default AI connection updated.'), variant: 'success');
    }

    public function confirmRemove(int $connectionId): void
    {
        $connection = AiConnection::query()->findOrFail($connectionId);
        $this->authorize('delete', $connection);

        $this->removingConnectionId = $connection->id;
        Flux::modal('confirm-remove-ai-connection')->show();
    }

    public function removeConnection(DeleteAiConnectionAction $deleteConnection): void
    {
        if ($this->removingConnectionId === null) {
            return;
        }

        $connection = AiConnection::query()->findOrFail($this->removingConnectionId);
        $deleteConnection($connection, Auth::user());

        if ($this->editingConnectionId === $connection->id) {
            $this->resetForm();
        }

        $this->removingConnectionId = null;
        unset($this->connections, $this->editingConnection, $this->removingConnection);

        Flux::modal('confirm-remove-ai-connection')->close();
        Flux::toast(__('AI connection removed.'), variant: 'success');
    }

    private function currentProvider(): AiProvider
    {
        return AiProvider::tryFrom($this->provider) ?? AiProvider::OpenRouter;
    }

    /**
     * @return list<string>
     */
    private function availableModelsFor(AiProvider $provider): array
    {
        if ($provider->isOpenRouter() && $this->allowPaidModels) {
            return array_values(array_unique([
                ...$provider->suggestedModels(),
                ...$provider->paidFallbackModels(),
            ]));
        }

        return $provider->suggestedModels();
    }

    private function syncModelSelectionFor(AiProvider $provider): void
    {
        $available = $this->availableModelsFor($provider);

        if ($available === []) {
            $this->selectedModel = self::CUSTOM_MODEL_OPTION;
            $this->model = '';

            return;
        }

        $this->selectedModel = $available[0];
        $this->model = $available[0];
    }

    private function resetForm(): void
    {
        $defaultProvider = AiProvider::OpenRouter;

        $this->editingConnectionId = null;
        $this->provider = $defaultProvider->value;
        $this->label = '';
        $this->baseUrl = '';
        $this->apiKey = '';
        $this->allowPaidModels = false;
        $this->syncModelSelectionFor($defaultProvider);
        $this->resetErrorBag();
    }

    private function fieldForException(BusinessRuleException $exception): string
    {
        return match ($exception::class) {
            EmptyAiApiKeyException::class => 'apiKey',
            EmptyAiModelException::class => 'model',
            InvalidAiBaseUrlException::class => 'baseUrl',
            default => 'provider',
        };
    }
};
?>

<section
    id="ai-connections"
    aria-labelledby="ai-connections-heading"
    class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-6 sm:p-8 flex flex-col gap-6 min-w-0"
>
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-3.5 min-w-0">
            <div class="size-10 rounded-xl bg-brand-tint text-accent-content flex items-center justify-center shrink-0">
                <flux:icon.sparkles class="size-5" aria-hidden="true" />
            </div>
            <div class="min-w-0">
                <h2 id="ai-connections-heading" class="font-headline font-bold text-xl text-ink tracking-tight">
                    {{ __('AI connections') }}
                </h2>
                <p class="text-sm text-ink-muted mt-1">
                    {{ __('Connect your own AI provider API keys to ask questions about your projects inside FunWork.') }}
                </p>
            </div>
        </div>
    </div>

    @if ($this->connections->isNotEmpty())
        <div class="flex flex-col gap-3">
            @foreach ($this->connections as $connection)
                <div
                    wire:key="ai-connection-{{ $connection->id }}"
                    class="rounded-xl border border-ghost bg-surface-low/40 dark:bg-surface-low/50 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                >
                    <div class="flex flex-col gap-1.5 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-headline font-semibold text-sm text-ink break-words">
                                {{ $connection->displayName() }}
                            </span>

                            @if (filled($connection->label))
                                <flux:badge size="sm" rounded color="zinc">
                                    {{ $connection->provider->label() }}
                                </flux:badge>
                            @endif

                            @if ($connection->is_default)
                                <flux:badge size="sm" rounded color="emerald">
                                    {{ __('Default') }}
                                </flux:badge>
                            @endif

                            @if ($connection->provider->isOpenRouter() && $connection->allow_paid_models)
                                <flux:badge size="sm" rounded color="amber">
                                    {{ __('Paid fallback on') }}
                                </flux:badge>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5 text-xs text-ink-muted">
                            <span class="font-mono text-ink break-words min-w-0">{{ $connection->model }}</span>
                            <span class="px-2 py-0.5 rounded-md bg-surface-card border border-ghost font-mono text-ink tracking-wider shrink-0">
                                {{ $connection->maskedApiKey() }}
                            </span>
                            @if ($connection->provider->isCustom() && filled($connection->base_url))
                                <span class="font-mono break-all min-w-0">{{ $connection->base_url }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center flex-wrap gap-2 shrink-0">
                        @if (! $connection->is_default)
                            <flux:button
                                type="button"
                                size="sm"
                                variant="filled"
                                wire:click="makeDefault({{ $connection->id }})"
                                data-test="make-default-ai-connection-{{ $connection->id }}"
                                class="max-md:min-h-11"
                            >
                                {{ __('Make default') }}
                            </flux:button>
                        @endif

                        <flux:button
                            type="button"
                            size="sm"
                            variant="ghost"
                            wire:click="edit({{ $connection->id }})"
                            data-test="edit-ai-connection-{{ $connection->id }}"
                            class="max-md:min-h-11"
                        >
                            {{ __('Edit') }}
                        </flux:button>

                        <flux:button
                            type="button"
                            size="sm"
                            variant="ghost"
                            wire:click="confirmRemove({{ $connection->id }})"
                            data-test="remove-ai-connection-{{ $connection->id }}"
                            class="text-danger! dark:text-red-400! max-md:min-h-11"
                        >
                            {{ __('Remove') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @php
        $activeProvider = \App\Enums\AiProvider::tryFrom($provider) ?? \App\Enums\AiProvider::OpenRouter;
        $suggestedModels = $activeProvider->isOpenRouter() && $allowPaidModels
            ? array_values(array_unique([...$activeProvider->suggestedModels(), ...$activeProvider->paidFallbackModels()]))
            : $activeProvider->suggestedModels();
        $showFreeTextModel = $activeProvider->isCustom() || $selectedModel === '__custom__';
    @endphp

    <form
        wire:submit="save"
        class="rounded-xl border border-ghost bg-surface-low/40 dark:bg-surface-low/50 p-4 sm:p-5 flex flex-col gap-4 min-w-0"
    >
        <div class="flex items-center justify-between gap-3">
            <h3 class="font-headline font-semibold text-base text-ink">
                {{ $this->editingConnection ? __('Edit connection') : __('Add connection') }}
            </h3>

            @if ($this->editingConnection)
                <flux:button type="button" size="sm" variant="ghost" wire:click="cancelEdit" class="max-md:min-h-11">
                    {{ __('Cancel') }}
                </flux:button>
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 min-w-0">
            <flux:field class="min-w-0 grid-cols-[minmax(0,1fr)]">
                <flux:label>{{ __('Provider') }}</flux:label>
                <flux:select variant="listbox" wire:model.live="provider" data-test="ai-provider-select" class="min-w-0">
                    @foreach (\App\Enums\AiProvider::cases() as $providerOption)
                        <flux:select.option :value="$providerOption->value">
                            {{ $providerOption->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="provider" />
            </flux:field>

            <flux:field class="min-w-0 grid-cols-[minmax(0,1fr)]">
                <flux:label>{{ __('Label (optional)') }}</flux:label>
                <flux:input
                    wire:model="label"
                    :placeholder="__('e.g. Work OpenAI')"
                />
                <flux:error name="label" />
            </flux:field>
        </div>

        @if ($activeProvider->isCustom())
            <flux:field class="min-w-0 grid-cols-[minmax(0,1fr)]">
                <flux:label>{{ __('Base URL') }}</flux:label>
                <flux:input
                    wire:model="baseUrl"
                    type="url"
                    placeholder="https://api.example.com/v1"
                    autocorrect="off"
                    autocapitalize="none"
                    spellcheck="false"
                />
                <flux:error name="baseUrl" />
                <flux:description>
                    {{ __('Must use https:// and point to an OpenAI-compatible API root.') }}
                </flux:description>
            </flux:field>
        @endif

        <div class="flex flex-col gap-4 min-w-0">
            @if (! $activeProvider->isCustom())
                <flux:field class="min-w-0 grid-cols-[minmax(0,1fr)]">
                    <flux:label>{{ __('Model') }}</flux:label>
                    <flux:select variant="listbox" wire:model.live="selectedModel" data-test="ai-model-select" class="min-w-0">
                        @foreach ($suggestedModels as $suggestedModel)
                            <flux:select.option :value="$suggestedModel">
                                {{ $suggestedModel }}
                            </flux:select.option>
                        @endforeach
                        <flux:select.option value="__custom__">
                            {{ __('Other (enter model ID)…') }}
                        </flux:select.option>
                    </flux:select>
                    @if (! $showFreeTextModel)
                        <flux:error name="model" />
                    @endif
                </flux:field>
            @endif

            @if ($showFreeTextModel)
                <flux:field>
                    <flux:label>
                        {{ $activeProvider->isCustom() ? __('Model') : __('Custom model ID') }}
                    </flux:label>
                    <flux:input
                        wire:model="model"
                        placeholder="e.g. gpt-5 or meta-llama/llama-3.3-70b-instruct:free"
                        autocorrect="off"
                        autocapitalize="none"
                        spellcheck="false"
                    />
                    <flux:error name="model" />
                </flux:field>
            @endif
        </div>

        <flux:field>
            <flux:label>{{ __('API key') }}</flux:label>
            <flux:input
                wire:model="apiKey"
                type="password"
                viewable
                autocomplete="new-password"
                autocorrect="off"
                autocapitalize="none"
                spellcheck="false"
                :placeholder="$this->editingConnection ? $this->editingConnection->maskedApiKey() : ($activeProvider->isOpenRouter() ? 'sk-or-v1-...' : 'sk-...')"
            />
            <flux:error name="apiKey" />
            <flux:description>
                @if ($this->editingConnection)
                    {{ __('Current key:') }} <span class="font-mono font-medium text-ink">{{ $this->editingConnection->maskedApiKey() }}</span>. {{ __('Leave blank to keep the existing key.') }}
                @else
                    {{ __('Stored encrypted. Only the last 4 characters are shown after saving.') }}
                @endif
            </flux:description>
        </flux:field>

        @if ($activeProvider->isOpenRouter())
            <flux:switch
                wire:model.live="allowPaidModels"
                :label="__('Allow paid models when free models fail')"
                :description="__('Off by default. When on, FunWork appends configured paid models after all free models fail, which spends your OpenRouter credits.')"
            />
        @endif

        <div class="flex justify-end pt-1">
            <flux:button type="submit" variant="primary" data-test="save-ai-connection" class="max-md:min-h-11">
                {{ $this->editingConnection ? __('Update connection') : __('Save connection') }}
            </flux:button>
        </div>
    </form>

    <div class="flex flex-col gap-3">
        <flux:callout
            color="blue"
            icon="information-circle"
            :heading="__('What gets sent')"
            :text="__('Your question and the project\'s tasks, decisions, notebooks and inbox are sent to the chosen AI provider. Free model providers may log or use this data. Do not use it for confidential projects.')"
        />

        <flux:callout
            variant="warning"
            icon="exclamation-triangle"
            :heading="__('Chat subscriptions do not include API access')"
            :text="__('A ChatGPT Plus or Claude Pro subscription does not include API access. Create an API key in the provider\'s developer console.')"
        />
    </div>

    <div
        aria-disabled="true"
        class="rounded-xl border border-ghost bg-surface-low/50 px-4 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 opacity-75 select-none"
    >
        <div class="flex items-start sm:items-center gap-3 min-w-0">
            <div class="size-9 rounded-lg bg-surface-card border border-ghost flex items-center justify-center text-ink-muted shrink-0">
                <flux:icon.computer-desktop class="size-5" aria-hidden="true" />
            </div>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-ink">
                        {{ __('Your computer (FunWork Companion)') }}
                    </span>
                    <flux:badge size="sm" rounded color="zinc">
                        {{ __('Coming soon') }}
                    </flux:badge>
                </div>
                <p class="text-xs text-ink-muted mt-0.5">
                    {{ __('Connect your local machine to use your Claude Code, Codex, or Gemini CLI subscription.') }}
                </p>
            </div>
        </div>
    </div>

    <flux:modal name="confirm-remove-ai-connection" class="w-[calc(100vw-2rem)] max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Remove AI connection?') }}</flux:heading>
                <flux:text class="mt-2">
                    @if ($this->removingConnection)
                        {{ __('This will permanently remove :name (:model). Conversations that used it will stay readable, and if it was your default connection another one will be promoted.', ['name' => $this->removingConnection->displayName(), 'model' => $this->removingConnection->model]) }}
                    @else
                        {{ __('This AI connection and its encrypted API key will be permanently deleted.') }}
                    @endif
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" class="max-md:min-h-11">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="removeConnection"
                    data-test="confirm-remove-ai-connection-button"
                    class="max-md:min-h-11"
                >
                    {{ __('Remove') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
