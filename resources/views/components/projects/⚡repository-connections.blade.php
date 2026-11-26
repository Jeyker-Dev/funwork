<?php

use App\Actions\RepositoryConnections\CreateRepositoryConnectionAction;
use App\Actions\RepositoryConnections\DeleteRepositoryConnectionAction;
use App\Actions\RepositoryConnections\RegenerateWebhookSecretAction;
use App\Actions\RepositoryConnections\SyncRepositoryConnectionNowAction;
use App\Actions\RepositoryConnections\UpdateRepositoryConnectionAction;
use App\Data\RepositoryConnections\RepositoryConnectionData;
use App\Enums\AreaColor;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\RepositoryConnections\AreaDoesNotBelongToProjectException;
use App\Exceptions\RepositoryConnections\DuplicateRepositoryConnectionException;
use App\Exceptions\RepositoryConnections\EmptyRepositoryTokenException;
use App\Exceptions\RepositoryConnections\InvalidBranchException;
use App\Exceptions\RepositoryConnections\InvalidDocsPathException;
use App\Exceptions\RepositoryConnections\InvalidRepositoryException;
use App\Models\Area;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Support\DateHelper;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public ?int $editingConnectionId = null;

    public string $repository = '';

    public string $branch = 'main';

    public string $docsPath = 'docs';

    public ?int $areaId = null;

    public string $token = '';

    public bool $revealWebhookSecret = false;

    public ?int $removingConnectionId = null;

    public function mount(Project $project): void
    {
        $this->authorize('viewAny', [RepositoryConnection::class, $project]);

        $this->project = $project;
    }

    #[On('areas-updated')]
    public function refreshAreas(): void
    {
        $this->project->refresh();
    }

    public function openCreate(): void
    {
        $this->authorize('create', [RepositoryConnection::class, $this->project]);

        $this->resetFlyoutFields();
        Flux::modal('repository-connection-flyout')->show();
    }

    public function openEdit(int $connectionId): void
    {
        $connection = $this->findConnectionOrFail($connectionId);
        $this->authorize('update', $connection);

        $this->editingConnectionId = $connection->id;
        $this->repository = $connection->repository;
        $this->branch = $connection->branch;
        $this->docsPath = $connection->docs_path;
        $this->areaId = $connection->area_id;
        $this->token = '';
        $this->revealWebhookSecret = false;
        $this->resetErrorBag();

        Flux::modal('repository-connection-flyout')->show();
    }

    public function closeFlyout(): void
    {
        $this->resetFlyoutFields();
    }

    public function save(
        CreateRepositoryConnectionAction $createConnection,
        UpdateRepositoryConnectionAction $updateConnection,
    ): void {
        $this->resetErrorBag();

        $area = null;

        if ($this->areaId !== null) {
            $area = Area::query()->find($this->areaId);

            if ($area === null) {
                $this->addError('areaId', __('The selected area no longer exists.'));

                return;
            }
        }

        $input = new RepositoryConnectionData(
            repository: trim($this->repository),
            branch: trim($this->branch),
            docsPath: trim($this->docsPath),
            token: $this->token,
            area: $area,
        );

        try {
            if ($this->editingConnectionId === null) {
                $connection = $createConnection($this->project, Auth::user(), $input);
                $this->token = '';
                $this->editingConnectionId = $connection->id;
                $this->repository = $connection->repository;
                $this->branch = $connection->branch;
                $this->docsPath = $connection->docs_path;
                $this->areaId = $connection->area_id;
                $this->revealWebhookSecret = false;

                Flux::toast('Repository connected. Copy the webhook URL and secret into GitHub.', variant: 'success');

                return;
            }

            $connection = $this->findConnectionOrFail($this->editingConnectionId);
            $updated = $updateConnection($connection, Auth::user(), $input);
            $this->token = '';
            $this->repository = $updated->repository;
            $this->branch = $updated->branch;
            $this->docsPath = $updated->docs_path;
            $this->areaId = $updated->area_id;

            Flux::modal('repository-connection-flyout')->close();
            Flux::toast('Repository connection updated. Sync queued.', variant: 'success');
        } catch (BusinessRuleException $e) {
            $this->addError($this->fieldForException($e), $e->getMessage());
        }
    }

    public function syncNow(int $connectionId, SyncRepositoryConnectionNowAction $syncConnectionNow): void
    {
        $connection = $this->findConnectionOrFail($connectionId);
        $syncConnectionNow($connection, Auth::user());

        Flux::toast('Repository sync queued.', variant: 'success');
    }

    public function toggleWebhookSecret(): void
    {
        $this->authorize('viewAny', [RepositoryConnection::class, $this->project]);

        if ($this->editingConnectionId === null) {
            return;
        }

        $connection = $this->findConnectionOrFail($this->editingConnectionId);
        $this->authorize('update', $connection);

        $this->revealWebhookSecret = ! $this->revealWebhookSecret;
    }

    public function getWebhookSecret(): string
    {
        $this->authorize('viewAny', [RepositoryConnection::class, $this->project]);

        if ($this->editingConnectionId === null) {
            abort(404);
        }

        $connection = $this->findConnectionOrFail($this->editingConnectionId);
        $this->authorize('update', $connection);

        return (string) $connection->webhook_secret;
    }

    public function regenerateWebhookSecret(RegenerateWebhookSecretAction $regenerateSecret): void
    {
        $this->authorize('viewAny', [RepositoryConnection::class, $this->project]);

        if ($this->editingConnectionId === null) {
            return;
        }

        $connection = $this->findConnectionOrFail($this->editingConnectionId);
        $regenerateSecret($connection, Auth::user());

        Flux::modal('confirm-regenerate-webhook-secret')->close();
        Flux::toast('Webhook secret regenerated.', variant: 'success');
    }

    public function confirmRemove(int $connectionId): void
    {
        $connection = $this->findConnectionOrFail($connectionId);
        $this->authorize('delete', $connection);

        $this->removingConnectionId = $connection->id;
        Flux::modal('confirm-remove-repository-connection')->show();
    }

    public function removeConnection(DeleteRepositoryConnectionAction $deleteConnection): void
    {
        $this->authorize('viewAny', [RepositoryConnection::class, $this->project]);

        if ($this->removingConnectionId === null) {
            return;
        }

        $connection = $this->findConnectionOrFail($this->removingConnectionId);
        $deleteConnection($connection, Auth::user());

        if ($this->editingConnectionId === $connection->id) {
            $this->resetFlyoutFields();
            Flux::modal('repository-connection-flyout')->close();
        }

        $this->removingConnectionId = null;
        Flux::modal('confirm-remove-repository-connection')->close();
        Flux::toast('Repository connection removed.', variant: 'success');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $connections = $this->project->repositoryConnections()
            ->with('area')
            ->orderBy('id')
            ->get();

        $areas = $this->project->areas()
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $editingConnection = $this->editingConnectionId !== null
            ? $connections->firstWhere('id', $this->editingConnectionId)
            : null;

        $removingConnection = $this->removingConnectionId !== null
            ? $connections->firstWhere('id', $this->removingConnectionId)
            : null;

        $revealedWebhookSecret = ($editingConnection !== null && $this->revealWebhookSecret)
            ? (string) $editingConnection->webhook_secret
            : null;

        return [
            'connections' => $connections,
            'areas' => $areas,
            'editingConnection' => $editingConnection,
            'removingConnection' => $removingConnection,
            'revealedWebhookSecret' => $revealedWebhookSecret,
        ];
    }

    private function findConnectionOrFail(int $connectionId): RepositoryConnection
    {
        return $this->project->repositoryConnections()->findOrFail($connectionId);
    }

    private function resetFlyoutFields(): void
    {
        $this->reset(['editingConnectionId', 'repository', 'areaId', 'token', 'revealWebhookSecret']);
        $this->branch = 'main';
        $this->docsPath = 'docs';
        $this->resetErrorBag();
    }

    private function fieldForException(BusinessRuleException $exception): string
    {
        return match ($exception::class) {
            InvalidRepositoryException::class => 'repository',
            InvalidBranchException::class => 'branch',
            InvalidDocsPathException::class => 'docsPath',
            EmptyRepositoryTokenException::class => 'token',
            AreaDoesNotBelongToProjectException::class,
            DuplicateRepositoryConnectionException::class => 'areaId',
            default => 'repository',
        };
    }
};
?>

<section
    id="repositories"
    aria-labelledby="repositories-settings-heading"
    class="bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-7 flex flex-col gap-6 scroll-mt-20"
>
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="max-w-xl">
            <h2 id="repositories-settings-heading" class="font-headline font-bold text-xl text-ink tracking-tight">
                {{ __('Repositories') }}
            </h2>
            <p class="text-sm text-ink-muted mt-1">
                {{ __("Keep this project's notebooks in sync with the docs folder of your GitHub repositories. Every push to the branch updates them automatically.") }}
            </p>
        </div>

        <flux:button
            type="button"
            variant="primary"
            icon="plus"
            wire:click="openCreate"
            data-test="connect-repository-trigger"
            class="self-start sm:self-center shrink-0 max-md:min-h-11"
        >
            {{ __('Connect repository') }}
        </flux:button>
    </div>

    @if ($connections->isEmpty())
        <div class="rounded-xl bg-surface-low/60 px-4 py-8 text-center">
            <p class="text-sm font-medium text-ink">
                {{ __('No repositories connected yet.') }}
            </p>
            <p class="text-xs text-ink-muted mt-1">
                {{ __('Connect a GitHub repository to automatically keep its docs folder synced as a project notebook.') }}
            </p>
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($connections as $connection)
                @php
                    $isBroken = $connection->isBroken();
                @endphp
                <div
                    wire:key="repository-connection-{{ $connection->id }}"
                    class="rounded-xl border border-ghost bg-surface-low/40 dark:bg-surface-low/50 p-4 sm:p-5 flex flex-col gap-4"
                >
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="size-10 rounded-xl bg-surface-card border border-ghost flex items-center justify-center text-ink shrink-0 mt-0.5" aria-hidden="true">
                                <svg class="size-5 fill-current" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" />
                                </svg>
                            </div>

                            <div class="flex flex-col gap-1.5 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-headline font-semibold text-base text-ink break-all">
                                        {{ $connection->repository }}
                                    </span>

                                    <flux:badge size="sm" rounded color="zinc" icon="code-bracket">
                                        {{ $connection->branch }}
                                    </flux:badge>

                                    <flux:badge size="sm" rounded color="zinc" icon="folder">
                                        {{ $connection->docs_path }}
                                    </flux:badge>

                                    @if ($connection->area)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-surface-card border border-ghost text-ink">
                                            <span class="size-1.5 rounded-full shrink-0 {{ AreaColor::dotClassFor($connection->area->color) }}" aria-hidden="true"></span>
                                            <span>{{ $connection->area->name }}</span>
                                        </span>
                                    @endif

                                    @if ($isBroken)
                                        <flux:badge size="sm" rounded color="red">
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="size-1.5 rounded-full bg-red-500" aria-hidden="true"></span>
                                                <span>{{ __('Broken') }}</span>
                                            </span>
                                        </flux:badge>
                                    @else
                                        <flux:badge size="sm" rounded color="green">
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="size-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                                <span>{{ __('Active') }}</span>
                                            </span>
                                        </flux:badge>
                                    @endif
                                </div>

                                <div class="text-xs text-ink-muted flex flex-wrap items-center gap-x-2 gap-y-1">
                                    @if ($connection->last_synced_at !== null)
                                        <span>{{ __('Last synced') }} {{ DateHelper::relativeAge($connection->last_synced_at) }}</span>
                                        @if (filled($connection->last_synced_sha))
                                            <span aria-hidden="true">·</span>
                                            <span class="font-mono bg-surface-card border border-ghost px-1.5 py-0.5 rounded text-ink">
                                                {{ __('commit') }} {{ substr($connection->last_synced_sha, 0, 7) }}
                                            </span>
                                        @endif
                                    @else
                                        <span>{{ __('Never synced') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center flex-wrap gap-2 self-end lg:self-center shrink-0">
                            <flux:button
                                type="button"
                                size="sm"
                                variant="filled"
                                icon="arrow-path"
                                wire:click="syncNow({{ $connection->id }})"
                                data-test="sync-now-{{ $connection->id }}"
                                class="max-md:min-h-11"
                            >
                                {{ __('Sync now') }}
                            </flux:button>

                            <flux:button
                                type="button"
                                size="sm"
                                variant="ghost"
                                wire:click="openEdit({{ $connection->id }})"
                                data-test="edit-connection-{{ $connection->id }}"
                                class="max-md:min-h-11"
                            >
                                {{ __('Edit') }}
                            </flux:button>

                            <flux:dropdown position="bottom" align="end">
                                <flux:button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    icon="ellipsis-horizontal"
                                    aria-label="{{ __('More options for :repository', ['repository' => $connection->repository]) }}"
                                    class="max-md:min-h-11 max-md:min-w-11"
                                />

                                <flux:menu>
                                    <flux:menu.item
                                        variant="danger"
                                        icon="trash"
                                        wire:click="confirmRemove({{ $connection->id }})"
                                        data-test="remove-connection-{{ $connection->id }}"
                                    >
                                        {{ __('Remove') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>

                    @if ($isBroken)
                        <flux:callout variant="danger" icon="exclamation-circle" inline>
                            <flux:callout.text>
                                {{ $connection->last_error ?: __('Repository sync failed. Update the token or settings to resume syncing.') }}
                            </flux:callout.text>

                            <x-slot name="actions">
                                <flux:button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    wire:click="openEdit({{ $connection->id }})"
                                    data-test="update-token-{{ $connection->id }}"
                                    class="max-md:min-h-11 bg-surface-card!"
                                >
                                    {{ __('Update token') }}
                                </flux:button>
                            </x-slot>
                        </flux:callout>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="rounded-xl border border-ghost bg-surface-low/50 p-4 sm:p-5">
        <flux:accordion transition>
            <flux:accordion.item :expanded="$connections->isEmpty()">
                <flux:accordion.heading>
                    <span class="inline-flex items-center gap-2.5 text-sm font-semibold text-ink">
                        <flux:icon name="information-circle" class="size-4 text-ink-muted shrink-0" aria-hidden="true" />
                        <span>{{ __('How to connect a repository') }}</span>
                    </span>
                </flux:accordion.heading>

                <flux:accordion.content>
                    <ol class="flex flex-col gap-3 pt-2 text-xs text-ink-muted leading-relaxed">
                        <li class="flex items-start gap-3">
                            <span class="size-5 rounded-full bg-surface-card border border-ghost text-ink flex items-center justify-center text-[11px] font-semibold shrink-0 mt-0.5">1</span>
                            <span>{{ __('Create a fine-grained token in GitHub with read-only access to Contents for this repository.') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="size-5 rounded-full bg-surface-card border border-ghost text-ink flex items-center justify-center text-[11px] font-semibold shrink-0 mt-0.5">2</span>
                            <span>{{ __('Add a webhook in the repository settings: paste the Payload URL and Secret from the connection panel, content type application/json, push events only.') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="size-5 rounded-full bg-surface-card border border-ghost text-ink flex items-center justify-center text-[11px] font-semibold shrink-0 mt-0.5">3</span>
                            <span>{{ __('Choose the branch, docs folder and area, then save. Use Sync now for an immediate sync, or push to GitHub to sync automatically.') }}</span>
                        </li>
                    </ol>
                </flux:accordion.content>
            </flux:accordion.item>
        </flux:accordion>
    </div>

    {{-- Connect / Edit Repository Flyout --}}
    <flux:modal
        name="repository-connection-flyout"
        variant="flyout"
        @close="closeFlyout"
        class="w-full max-w-md sm:max-w-lg"
    >
        <form wire:submit="save" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">
                    {{ $editingConnection ? __('Edit repository connection') : __('Connect repository') }}
                </flux:heading>
                <flux:text class="mt-1">
                    {{ __('Sync notebooks with markdown files in a GitHub repository.') }}
                </flux:text>
            </div>

            <flux:input
                wire:model="repository"
                :label="__('Repository')"
                placeholder="owner/name (e.g. acme/web-docs)"
                autocorrect="off"
                autocapitalize="none"
                spellcheck="false"
                required
            />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input
                    wire:model="branch"
                    :label="__('Branch')"
                    placeholder="main"
                    autocorrect="off"
                    autocapitalize="none"
                    spellcheck="false"
                    required
                />

                <flux:input
                    wire:model="docsPath"
                    :label="__('Docs folder')"
                    placeholder="docs"
                    autocorrect="off"
                    autocapitalize="none"
                    spellcheck="false"
                    required
                />
            </div>

            <flux:select
                variant="listbox"
                wire:model="areaId"
                :label="__('Area')"
                :placeholder="__('No area')"
            >
                <flux:select.option :value="null">{{ __('No area') }}</flux:select.option>
                @foreach ($areas as $area)
                    <flux:select.option :value="$area->id">{{ $area->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:field>
                <flux:label>{{ __('Personal access token') }}</flux:label>
                <flux:input
                    wire:model="token"
                    type="password"
                    viewable
                    autocomplete="new-password"
                    autocorrect="off"
                    autocapitalize="none"
                    spellcheck="false"
                    :placeholder="$editingConnection ? $editingConnection->maskedToken() : 'github_pat_...'"
                    :required="! $editingConnection"
                />
                <flux:error name="token" />
                <flux:description>
                    @if ($editingConnection)
                        {{ __('Current token:') }} <span class="font-mono font-medium text-ink">{{ $editingConnection->maskedToken() }}</span>. {{ __('Leave blank to keep the existing token.') }}
                    @else
                        {{ __('Stored encrypted. Only the last 4 characters are shown after saving.') }}
                    @endif
                </flux:description>
            </flux:field>

            @if ($editingConnection)
                @php
                    $webhookUrl = route('webhooks.github.repository-connections', $editingConnection);
                @endphp
                <div class="rounded-xl border border-ghost bg-surface-low/60 p-4 flex flex-col gap-3.5">
                    <div class="flex items-center gap-2">
                        <flux:icon name="link" class="size-4 text-accent-content shrink-0" aria-hidden="true" />
                        <span class="text-xs font-semibold text-ink">{{ __('Webhook settings') }}</span>
                    </div>

                    {{-- Payload URL --}}
                    <div
                        class="flex flex-col gap-1"
                        x-data="{
                            copied: false,
                            failed: false,
                            feedback: '',
                            timer: null,
                            async copyUrl() {
                                const text = @js($webhookUrl);
                                clearTimeout(this.timer);
                                this.copied = false;
                                this.failed = false;
                                this.feedback = '';

                                try {
                                    if (navigator.clipboard && window.isSecureContext) {
                                        await navigator.clipboard.writeText(text);
                                    } else {
                                        const textarea = document.createElement('textarea');
                                        textarea.value = text;
                                        textarea.setAttribute('readonly', '');
                                        textarea.style.cssText = 'position:fixed;inset-block-start:0;opacity:0';
                                        document.body.appendChild(textarea);
                                        textarea.select();
                                        const ok = document.execCommand('copy');
                                        textarea.remove();
                                        if (!ok) throw new Error('Copy failed');
                                    }
                                    this.copied = true;
                                    this.feedback = @js(__('Copied'));
                                } catch {
                                    this.failed = true;
                                    this.feedback = @js(__('Copy failed'));
                                }

                                this.timer = setTimeout(() => {
                                    this.copied = false;
                                    this.failed = false;
                                    this.feedback = '';
                                }, 2000);
                            }
                        }"
                    >
                        <label for="webhook-payload-url-{{ $editingConnection->id }}" class="text-xs text-ink-muted">
                            {{ __('Payload URL') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                id="webhook-payload-url-{{ $editingConnection->id }}"
                                type="text"
                                readonly
                                value="{{ $webhookUrl }}"
                                class="flex-1 min-w-0 rounded-lg border border-ghost bg-surface-card px-3 py-2 font-mono text-xs text-ink focus:outline-none focus:ring-2 focus:ring-brand"
                            />
                            <button
                                type="button"
                                x-on:click="copyUrl()"
                                aria-label="{{ __('Copy Payload URL') }}"
                                class="inline-flex items-center justify-center min-h-9 min-w-9 max-md:min-h-11 max-md:min-w-11 rounded-lg border border-ghost bg-surface-card text-ink-muted hover:text-ink hover:bg-surface-high transition-colors cursor-pointer shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                <flux:icon x-show="!copied" name="clipboard-document" variant="mini" class="size-4" aria-hidden="true" />
                                <flux:icon x-show="copied" x-cloak name="check" variant="mini" class="size-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                            </button>
                            <span role="status" class="sr-only" x-text="feedback"></span>
                        </div>
                        <span x-show="copied || failed" x-cloak class="text-[11px] font-medium" :class="failed ? 'text-danger dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'" x-text="feedback"></span>
                    </div>

                    {{-- Secret --}}
                    <div
                        class="flex flex-col gap-1"
                        x-data="{
                            copied: false,
                            failed: false,
                            feedback: '',
                            timer: null,
                            async copySecret() {
                                clearTimeout(this.timer);
                                this.copied = false;
                                this.failed = false;
                                this.feedback = '';

                                try {
                                    const secret = await $wire.getWebhookSecret();
                                    if (navigator.clipboard && window.isSecureContext) {
                                        await navigator.clipboard.writeText(secret);
                                    } else {
                                        const textarea = document.createElement('textarea');
                                        textarea.value = secret;
                                        textarea.setAttribute('readonly', '');
                                        textarea.style.cssText = 'position:fixed;inset-block-start:0;opacity:0';
                                        document.body.appendChild(textarea);
                                        textarea.select();
                                        const ok = document.execCommand('copy');
                                        textarea.remove();
                                        if (!ok) throw new Error('Copy failed');
                                    }
                                    this.copied = true;
                                    this.feedback = @js(__('Copied'));
                                } catch {
                                    this.failed = true;
                                    this.feedback = @js(__('Copy failed'));
                                }

                                this.timer = setTimeout(() => {
                                    this.copied = false;
                                    this.failed = false;
                                    this.feedback = '';
                                }, 2000);
                            }
                        }"
                    >
                        <label for="webhook-secret-{{ $editingConnection->id }}" class="text-xs text-ink-muted">
                            {{ __('Secret') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                id="webhook-secret-{{ $editingConnection->id }}"
                                type="text"
                                readonly
                                value="{{ $revealedWebhookSecret ?? str_repeat('•', 24) }}"
                                class="flex-1 min-w-0 rounded-lg border border-ghost bg-surface-card px-3 py-2 font-mono text-xs text-ink focus:outline-none focus:ring-2 focus:ring-brand"
                            />
                            <button
                                type="button"
                                wire:click="toggleWebhookSecret"
                                aria-pressed="{{ $revealedWebhookSecret !== null ? 'true' : 'false' }}"
                                aria-label="{{ $revealedWebhookSecret !== null ? __('Hide webhook secret') : __('Reveal webhook secret') }}"
                                data-test="toggle-webhook-secret"
                                class="inline-flex items-center justify-center min-h-9 min-w-9 max-md:min-h-11 max-md:min-w-11 px-2.5 rounded-lg border border-ghost bg-surface-card text-xs font-medium text-ink-muted hover:text-ink hover:bg-surface-high transition-colors cursor-pointer shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                {{ $revealedWebhookSecret !== null ? __('Hide') : __('Reveal') }}
                            </button>
                            <button
                                type="button"
                                x-on:click="copySecret()"
                                aria-label="{{ __('Copy webhook secret') }}"
                                class="inline-flex items-center justify-center min-h-9 min-w-9 max-md:min-h-11 max-md:min-w-11 rounded-lg border border-ghost bg-surface-card text-ink-muted hover:text-ink hover:bg-surface-high transition-colors cursor-pointer shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                <flux:icon x-show="!copied" name="clipboard-document" variant="mini" class="size-4" aria-hidden="true" />
                                <flux:icon x-show="copied" x-cloak name="check" variant="mini" class="size-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                            </button>
                            <span role="status" class="sr-only" x-text="feedback"></span>
                        </div>
                        <span x-show="copied || failed" x-cloak class="text-[11px] font-medium" :class="failed ? 'text-danger dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'" x-text="feedback"></span>
                    </div>

                    <div>
                        <flux:modal.trigger name="confirm-regenerate-webhook-secret">
                            <button
                                type="button"
                                data-test="regenerate-webhook-secret-trigger"
                                class="inline-flex items-center min-h-8 max-md:min-h-11 text-xs font-medium text-accent-content hover:underline cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded"
                            >
                                {{ __('Regenerate secret') }}
                            </button>
                        </flux:modal.trigger>
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" class="max-md:min-h-11">
                        {{ $editingConnection ? __('Close') : __('Cancel') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" data-test="save-repository-connection" class="max-md:min-h-11">
                    {{ __('Save connection') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Regenerate Webhook Secret Confirmation Modal --}}
    <flux:modal name="confirm-regenerate-webhook-secret" class="w-[calc(100vw-2rem)] max-w-md space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Regenerate webhook secret?') }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('The current secret will stop working immediately. You will need to update the secret in your GitHub repository webhook settings.') }}
            </flux:text>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button type="button" variant="ghost" class="max-md:min-h-11">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button
                type="button"
                variant="danger"
                wire:click="regenerateWebhookSecret"
                data-test="confirm-regenerate-webhook-secret-button"
                class="max-md:min-h-11"
            >
                {{ __('Regenerate secret') }}
            </flux:button>
        </div>
    </flux:modal>

    {{-- Remove Connection Confirmation Modal --}}
    <flux:modal name="confirm-remove-repository-connection" class="w-[calc(100vw-2rem)] max-w-md space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Remove repository connection?') }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('FunWork will stop syncing :repository. Your existing notebook and its pages will be kept.', ['repository' => $removingConnection?->repository ?? __('this repository')]) }}
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
                data-test="confirm-remove-repository-connection-button"
                class="max-md:min-h-11"
            >
                {{ __('Remove connection') }}
            </flux:button>
        </div>
    </flux:modal>
</section>
