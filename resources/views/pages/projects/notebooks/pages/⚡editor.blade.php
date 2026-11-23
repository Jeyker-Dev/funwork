<?php

use App\Actions\Notebooks\CreateNotebookPageAction;
use App\Actions\Notebooks\DeleteNotebookPageAction;
use App\Actions\Notebooks\SaveNotebookPageAction;
use App\Enums\AreaColor;
use App\Exceptions\Notebooks\NotebookPageCannotBeEmptyException;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public Notebook $notebook;

    public NotebookPage $page;

    #[Url(except: false)]
    public bool $focus = false;

    public string $title = '';

    public string $content = '';

    public bool $hasError = false;

    public string $errorMessage = '';

    public function mount(Project $project, Notebook $notebook, NotebookPage $page): void
    {
        $this->authorize('view', $project);
        $this->authorize('view', $notebook);
        $this->authorize('update', $page);

        $this->project = $project;
        $this->notebook = $notebook->loadMissing('area');
        $this->page = $page;

        $this->title = is_array($page->content) ? ($page->content['title'] ?? '') : '';
        $this->content = is_array($page->content) ? ($page->content['body'] ?? '') : (is_string($page->content) ? $page->content : '');
    }

    public function rendering(View $view): void
    {
        if ($this->focus) {
            $view->layout('layouts.focus', ['title' => $this->notebook->name]);

            return;
        }

        $view->layout('layouts.app', [
            'project' => $this->project,
            'title' => $this->notebook->name,
        ]);
    }

    /**
     * @return Collection<int, NotebookPage>
     */
    #[Computed]
    public function pages(): Collection
    {
        return $this->notebook->pages()
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function totalPages(): int
    {
        return $this->pages->count();
    }

    #[Computed]
    public function currentPageIndex(): int
    {
        $position = $this->pages->search(fn (NotebookPage $item): bool => $item->id === $this->page->id);

        return $position === false ? 1 : $position + 1;
    }

    #[Computed]
    public function previousPage(): ?NotebookPage
    {
        $index = $this->currentPageIndex - 1;

        return $index > 0 ? $this->pages->get($index - 1) : null;
    }

    #[Computed]
    public function nextPage(): ?NotebookPage
    {
        return $this->pages->get($this->currentPageIndex);
    }

    #[Computed]
    public function previewHtml(): HtmlString
    {
        return Markdown::toHtml($this->content);
    }

    public function updatedTitle(): void
    {
        $this->save();
    }

    public function updatedContent(): void
    {
        $this->save();
    }

    public function save(?SaveNotebookPageAction $saveAction = null): void
    {
        $this->authorize('update', $this->page);

        $this->resetErrorBag();
        $this->hasError = false;

        $saveAction ??= app(SaveNotebookPageAction::class);

        try {
            $saveAction($this->page, $this->title, $this->content);
            unset($this->pages, $this->previewHtml);
        } catch (NotebookPageCannotBeEmptyException $e) {
            $this->hasError = true;
            $this->errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $this->hasError = true;
            $this->errorMessage = 'Failed to save.';
            Log::error('Autosave failed: '.$e->getMessage(), [
                'page_id' => $this->page->id,
                'exception' => $e,
            ]);
        }
    }

    public function retrySave(): void
    {
        $this->save();
    }

    public function deletePage(?DeleteNotebookPageAction $deleteAction = null): void
    {
        $this->authorize('delete', $this->page);

        if ($this->totalPages <= 1) {
            $this->title = '';
            $this->content = '';
            $this->save();

            return;
        }

        $deleteAction ??= app(DeleteNotebookPageAction::class);
        $fallbackPage = $deleteAction($this->page);

        if ($fallbackPage) {
            $params = [
                'project' => $this->project,
                'notebook' => $this->notebook,
                'page' => $fallbackPage,
            ];

            if ($this->focus) {
                $params['focus'] = 1;
            }

            $this->redirectRoute('project.notebooks.pages.edit', $params);

            return;
        }

        $this->redirectRoute('project.notebooks', ['project' => $this->project]);
    }

    public function createNextPage(?CreateNotebookPageAction $createAction = null): void
    {
        $this->authorize('create', [NotebookPage::class, $this->notebook]);

        $createAction ??= app(CreateNotebookPageAction::class);
        $newPage = $createAction($this->notebook, Auth::user());

        $params = [
            'project' => $this->project,
            'notebook' => $this->notebook,
            'page' => $newPage,
        ];

        if ($this->focus) {
            $params['focus'] = 1;
        }

        $this->redirectRoute('project.notebooks.pages.edit', $params);
    }

    private function pageDisplayTitle(NotebookPage $notebookPage): string
    {
        $pageTitle = is_array($notebookPage->content) ? ($notebookPage->content['title'] ?? '') : '';

        return filled($pageTitle) ? (string) $pageTitle : 'Untitled page';
    }
};
?>

@php
    $userTimezone = auth()->user()->timezone ?? config('app.timezone');
    $currentDisplayTitle = filled($title) ? $title : 'Untitled page';
    $standardModeUrl = route('project.notebooks.pages.edit', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page,
    ]);
    $focusModeUrl = route('project.notebooks.pages.edit', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page,
        'focus' => 1,
    ]);
@endphp

<div
    @class([
        'w-full min-w-0',
        'flex flex-col min-h-screen max-w-5xl mx-auto px-4 sm:px-6 pt-8 pb-4' => $focus,
        'max-w-7xl mx-auto py-2 sm:py-4' => ! $focus,
    ])
    x-data="{
        mode: 'write',
        hasPendingSync: false,
        mobilePagesOpen: false,
        isMac: /Mac|iPhone|iPad|iPod/i.test(navigator.platform || ''),
        init() {
            this.$wire.$hook('commit', ({ succeed }) => {
                succeed(() => {
                    this.hasPendingSync = false;
                });
            });

            this.$watch('mode', async (value) => {
                if (value === 'preview' && this.hasPendingSync) {
                    this.hasPendingSync = false;
                    await this.$wire.$commit();
                }
            });
        },
        toggleMode(focusEditor = false) {
            this.mode = this.mode === 'write' ? 'preview' : 'write';

            if (this.mode === 'write' && focusEditor) {
                this.$nextTick(() => this.$refs.bodyInput?.focus());
            }
        },
        handleKeydown(e) {
            if (! this.$el.isConnected) {
                return;
            }

            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                this.$wire.createNextPage();

                return;
            }

            if ((e.ctrlKey || e.metaKey) && e.shiftKey && ! e.altKey && e.key.toLowerCase() === 'p' && ! e.repeat) {
                e.preventDefault();
                this.toggleMode(true);
            }
        }
    }"
    @keydown.window="handleKeydown"
>
    @if ($focus)
        <header class="flex flex-wrap justify-between items-center gap-3 mb-8 sm:mb-12">
            <div class="flex items-center gap-3 min-w-0">
                <a
                    href="{{ $standardModeUrl }}"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted hover:text-ink transition-colors max-md:min-h-11"
                >
                    <flux:icon.chevron-left class="size-4 shrink-0" stroke-width="2" />
                    <span class="truncate">{{ __('Exit focus mode') }}</span>
                </a>
            </div>

            <div class="flex items-center gap-2 text-sm font-medium text-ink-muted min-w-0 max-w-full">
                @if ($notebook->area)
                    <span class="size-2 rounded-full shrink-0 {{ AreaColor::dotClassFor($notebook->area->color) }}" aria-hidden="true"></span>
                @endif
                <span class="truncate">{{ $notebook->name }}</span>
            </div>

            <div class="flex justify-end items-center gap-2 sm:gap-3">
                <div
                    class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-surface-card border border-ghost text-ink-muted transition-opacity duration-300"
                    wire:loading.class="opacity-50"
                    wire:target="title, content, save"
                >
                    @if ($hasError)
                        <flux:icon.exclamation-circle class="size-4 text-danger dark:text-red-400" />
                        <span class="text-danger dark:text-red-400">{{ $errorMessage }}</span>
                        <button type="button" wire:click="retrySave" class="font-semibold underline ml-1 text-danger dark:text-red-400">
                            {{ __('Retry') }}
                        </button>
                    @else
                        <flux:icon.cloud class="size-4 text-accent-content" />
                        <span>{{ __('Autosaved') }}</span>
                    @endif
                </div>

                <flux:dropdown>
                    <flux:button
                        icon="ellipsis-horizontal"
                        variant="ghost"
                        size="sm"
                        aria-label="{{ __('Page options') }}"
                        class="text-ink-subtle hover:text-ink max-md:min-h-11 max-md:min-w-11"
                    />
                    <flux:menu>
                        <flux:menu.item wire:click="deletePage" icon="trash" variant="danger">
                            {{ __('Delete Page') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </header>

        @if ($hasError && trim($content) === '' && trim($title) === '')
            <div class="bg-danger-tint dark:bg-red-950/40 text-danger dark:text-red-300 p-4 rounded-xl mb-6 flex flex-wrap justify-between items-center gap-3 border border-ghost max-w-3xl mx-auto w-full">
                <span class="text-sm font-medium">{{ __('You cleared the page. We prevented silent deletion.') }}</span>
                <flux:button type="button" variant="danger" size="sm" wire:click="deletePage">
                    {{ __('Delete Page') }}
                </flux:button>
            </div>
        @endif

        <div class="flex-grow flex flex-col w-full max-w-3xl mx-auto min-w-0">
            <input
                type="text"
                wire:model.live.debounce.1000ms="title"
                placeholder="Optional title..."
                aria-label="Page title"
                x-data="{ focused: false }"
                @focus="focused = true"
                @blur="focused = false"
                :class="($wire.title || focused) ? 'text-3xl sm:text-4xl mb-6 opacity-100' : 'text-xl mb-4 opacity-50 hover:opacity-100'"
                class="w-full bg-transparent border-0 ring-0 focus:ring-0 outline-none font-headline font-bold text-ink placeholder-ink-subtle px-0 transition-all duration-200"
            />

            <div class="flex items-center justify-between gap-3 mb-6 pb-3 border-b border-ghost" wire:ignore>
                <flux:radio.group variant="segmented" size="sm" x-model="mode" aria-label="Editor mode">
                    <flux:radio value="write" icon="pencil-square" checked>{{ __('Write') }}</flux:radio>
                    <flux:radio value="preview" icon="eye">{{ __('Preview') }}</flux:radio>
                </flux:radio.group>

                <span class="hidden sm:inline text-xs text-ink-subtle" x-text="isMac ? '⌘⇧P to toggle' : 'Ctrl+Shift+P to toggle'"></span>
            </div>

            <textarea
                x-ref="bodyInput"
                x-show="mode === 'write'"
                wire:model.live.debounce.1000ms="content"
                @input="hasPendingSync = true"
                aria-label="Page content"
                class="w-full flex-grow min-h-[24rem] bg-transparent border-0 ring-0 focus:ring-0 resize-none outline-none text-base sm:text-lg leading-relaxed text-ink placeholder-ink-subtle px-0"
                placeholder="Start writing..."
            ></textarea>

            <div
                x-show="mode === 'preview'"
                x-cloak
                style="display: none;"
                class="flex-grow min-w-0 w-full overflow-x-hidden"
            >
                @if (blank($this->previewHtml->toHtml()))
                    <p class="text-base leading-relaxed text-ink-subtle italic">{{ __('Nothing to preview') }}</p>
                @else
                    <div class="markdown-content min-w-0">{!! $this->previewHtml !!}</div>
                @endif
            </div>
        </div>

        <footer class="flex items-center justify-between py-6 max-w-3xl mx-auto w-full mt-auto border-t border-ghost">
            <div class="flex-1">
                @if ($this->previousPage)
                    <a
                        href="{{ route('project.notebooks.pages.edit', ['project' => $project, 'notebook' => $notebook, 'page' => $this->previousPage, 'focus' => 1]) }}"
                        wire:navigate
                        class="text-sm font-medium text-ink-muted hover:text-ink transition-colors inline-flex items-center gap-1.5"
                    >
                        <flux:icon.chevron-left class="size-4 shrink-0" stroke-width="2" />
                        <span>{{ __('Previous Page') }}</span>
                    </a>
                @endif
            </div>

            <div class="text-xs font-medium text-ink-subtle tabular-nums">
                {{ $this->currentPageIndex }} of {{ $this->totalPages }}
            </div>

            <div class="flex-1 flex justify-end">
                @if ($this->nextPage)
                    <a
                        href="{{ route('project.notebooks.pages.edit', ['project' => $project, 'notebook' => $notebook, 'page' => $this->nextPage, 'focus' => 1]) }}"
                        wire:navigate
                        class="text-sm font-medium text-ink-muted hover:text-ink transition-colors inline-flex items-center gap-1.5"
                    >
                        <span>{{ __('Next Page') }}</span>
                        <flux:icon.chevron-right class="size-4 shrink-0" stroke-width="2" />
                    </a>
                @else
                    <button
                        type="button"
                        wire:click="createNextPage"
                        class="text-sm font-medium text-ink-muted hover:text-ink transition-colors inline-flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>{{ __('New Page') }}</span>
                        <flux:icon.plus class="size-4 shrink-0" stroke-width="2" />
                    </button>
                @endif
            </div>
        </footer>
    @else
        {{-- Mobile Header & Collapsible Page Index (< lg) --}}
        <div class="lg:hidden mb-4 space-y-3 min-w-0">
            <div class="flex items-center justify-between gap-2">
                <a
                    href="{{ route('project.notebooks', ['project' => $project]) }}"
                    wire:navigate
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted hover:text-ink transition-colors min-h-11"
                >
                    <flux:icon.chevron-left class="size-4 shrink-0" stroke-width="2" />
                    <span>{{ __('All notebooks') }}</span>
                </a>

                <a
                    href="{{ $focusModeUrl }}"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-muted hover:text-ink px-3 py-2 rounded-lg bg-surface-card border border-ghost min-h-11"
                >
                    <flux:icon.arrows-pointing-out class="size-3.5 shrink-0" />
                    <span>{{ __('Focus mode') }}</span>
                </a>
            </div>

            <div class="space-y-1.5 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($notebook->area)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-surface-card border border-ghost text-ink-muted">
                            <span class="size-1.5 rounded-full shrink-0 {{ AreaColor::dotClassFor($notebook->area->color) }}" aria-hidden="true"></span>
                            <span>{{ $notebook->area->name }}</span>
                        </span>
                    @endif
                    <span class="text-xs text-ink-subtle tabular-nums">
                        {{ $this->totalPages }} {{ Str::plural('page', $this->totalPages) }}
                    </span>
                </div>
                <h1 class="font-headline font-bold text-xl tracking-tight text-ink break-words">
                    {{ $notebook->name }}
                </h1>
            </div>

            <div>
                <button
                    type="button"
                    @click="mobilePagesOpen = !mobilePagesOpen"
                    :aria-expanded="mobilePagesOpen.toString()"
                    aria-controls="mobile-page-index"
                    class="w-full flex items-center justify-between gap-3 px-4 py-2.5 min-h-11 rounded-2xl bg-surface-card border border-ghost shadow-ambient text-left cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    <span class="flex items-center gap-2 min-w-0">
                        <flux:icon.document-text class="size-4 text-accent-content shrink-0" />
                        <span class="text-sm font-semibold text-ink truncate">{{ $currentDisplayTitle }}</span>
                    </span>
                    <span class="flex items-center gap-2 shrink-0">
                        <span class="text-xs font-medium text-ink-muted tabular-nums">{{ $this->currentPageIndex }} of {{ $this->totalPages }}</span>
                        <flux:icon.chevron-down class="size-4 text-ink-subtle transition-transform duration-150" x-bind:class="mobilePagesOpen ? 'rotate-180' : ''" />
                    </span>
                </button>

                <div
                    id="mobile-page-index"
                    x-show="mobilePagesOpen"
                    x-cloak
                    style="display: none;"
                    class="mt-2 bg-surface-card rounded-2xl border border-ghost shadow-ambient p-2 space-y-2"
                >
                    <nav aria-label="Notebook pages" class="max-h-64 overflow-y-auto space-y-1">
                        @foreach ($this->pages as $navPage)
                            @php
                                $isActive = $navPage->id === $page->id;
                                $navTitle = $this->pageDisplayTitle($navPage);
                            @endphp
                            <a
                                wire:key="mobile-page-link-{{ $navPage->id }}"
                                href="{{ route('project.notebooks.pages.edit', ['project' => $project, 'notebook' => $notebook, 'page' => $navPage]) }}"
                                wire:navigate
                                @if ($isActive) aria-current="page" @endif
                                @class([
                                    'flex items-center min-h-11 px-3.5 py-2 rounded-xl text-sm transition-colors truncate',
                                    'bg-surface-low dark:bg-surface-high text-accent-content font-semibold shadow-[inset_3px_0_0_var(--color-brand)]' => $isActive,
                                    'text-ink-muted hover:text-ink hover:bg-surface-low/60 font-medium' => ! $isActive,
                                ])
                            >
                                <span class="truncate">{{ $navTitle }}</span>
                            </a>
                        @endforeach
                    </nav>

                    <div class="pt-1 border-t border-ghost">
                        <flux:button variant="ghost" icon="plus" wire:click="createNextPage" class="w-full justify-center min-h-11">
                            {{ __('New page') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Desktop Two-Pane Layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start min-w-0">
            {{-- Left Pane: Page Index --}}
            <aside class="hidden lg:flex lg:col-span-4 xl:col-span-3 flex-col justify-between bg-surface-low rounded-2xl border border-ghost p-4 lg:sticky lg:top-20 max-h-[calc(100vh-7rem)] min-w-0">
                <div class="flex flex-col min-h-0 flex-1 space-y-4">
                    <div>
                        <a
                            href="{{ route('project.notebooks', ['project' => $project]) }}"
                            wire:navigate
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-muted hover:text-ink transition-colors py-1"
                        >
                            <flux:icon.chevron-left class="size-3.5 shrink-0" stroke-width="2" />
                            <span>{{ __('All notebooks') }}</span>
                        </a>
                    </div>

                    <div class="space-y-2 px-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($notebook->area)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-surface-card dark:bg-surface-high border border-ghost text-ink-muted">
                                    <span class="size-1.5 rounded-full shrink-0 {{ AreaColor::dotClassFor($notebook->area->color) }}" aria-hidden="true"></span>
                                    <span>{{ $notebook->area->name }}</span>
                                </span>
                            @endif
                            <span class="text-xs text-ink-subtle tabular-nums">
                                {{ $this->totalPages }} {{ Str::plural('page', $this->totalPages) }}
                            </span>
                        </div>

                        <h1 class="font-headline font-bold text-xl tracking-tight text-ink break-words">
                            {{ $notebook->name }}
                        </h1>
                    </div>

                    <nav aria-label="Notebook pages" class="flex-1 min-h-0 overflow-y-auto space-y-1 pr-1 tasks-scroll">
                        @foreach ($this->pages as $navPage)
                            @php
                                $isActive = $navPage->id === $page->id;
                                $navTitle = $this->pageDisplayTitle($navPage);
                            @endphp
                            <a
                                wire:key="desktop-page-link-{{ $navPage->id }}"
                                href="{{ route('project.notebooks.pages.edit', ['project' => $project, 'notebook' => $notebook, 'page' => $navPage]) }}"
                                wire:navigate
                                @if ($isActive) aria-current="page" @endif
                                @class([
                                    'block px-3.5 py-2.5 rounded-xl text-sm transition-colors truncate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                                    'bg-surface-card dark:bg-surface-high text-accent-content font-semibold shadow-[inset_3px_0_0_var(--color-brand)]' => $isActive,
                                    'text-ink-muted hover:text-ink hover:bg-surface-card/60 dark:hover:bg-surface-high/60 font-medium' => ! $isActive,
                                ])
                            >
                                {{ $navTitle }}
                            </a>
                        @endforeach
                    </nav>
                </div>

                <div class="pt-3 mt-3 border-t border-ghost shrink-0">
                    <flux:button variant="ghost" icon="plus" wire:click="createNextPage" class="w-full justify-center">
                        {{ __('New page') }}
                    </flux:button>
                </div>
            </aside>

            {{-- Right Pane: Editor --}}
            <section class="lg:col-span-8 xl:col-span-9 bg-surface-card rounded-2xl border border-ghost shadow-ambient p-5 sm:p-8 lg:p-10 flex flex-col justify-between min-h-[38rem] min-w-0">
                <div class="flex-1 flex flex-col min-w-0">
                    <header class="flex flex-wrap items-center justify-between gap-3 pb-5 mb-6 border-b border-ghost">
                        <div class="flex items-center gap-3" wire:ignore>
                            <flux:radio.group variant="segmented" size="sm" x-model="mode" aria-label="Editor mode">
                                <flux:radio value="write" icon="pencil-square" checked>{{ __('Write') }}</flux:radio>
                                <flux:radio value="preview" icon="eye">{{ __('Preview') }}</flux:radio>
                            </flux:radio.group>

                            <span class="hidden sm:inline text-xs text-ink-subtle" x-text="isMac ? '⌘⇧P to toggle' : 'Ctrl+Shift+P to toggle'"></span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                            <div
                                class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-surface-low text-ink-muted transition-opacity duration-300"
                                wire:loading.class="opacity-50"
                                wire:target="title, content, save"
                            >
                                @if ($hasError)
                                    <flux:icon.exclamation-circle class="size-3.5 text-danger dark:text-red-400" />
                                    <span class="text-danger dark:text-red-400">{{ $errorMessage }}</span>
                                    <button type="button" wire:click="retrySave" class="font-semibold underline ml-1 text-danger dark:text-red-400 cursor-pointer">
                                        {{ __('Retry') }}
                                    </button>
                                @else
                                    <flux:icon.cloud class="size-3.5 text-accent-content" />
                                    <span>{{ __('Autosaved') }}</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-1">
                                @if ($this->previousPage)
                                    <a
                                        href="{{ route('project.notebooks.pages.edit', ['project' => $project, 'notebook' => $notebook, 'page' => $this->previousPage]) }}"
                                        wire:navigate
                                        aria-label="{{ __('Previous page') }}"
                                        class="p-1.5 max-md:min-h-11 max-md:min-w-11 inline-flex items-center justify-center rounded-lg text-ink-muted hover:text-ink hover:bg-surface-low transition-colors"
                                    >
                                        <flux:icon.chevron-left class="size-4" stroke-width="2" />
                                    </a>
                                @else
                                    <span aria-disabled="true" class="p-1.5 inline-flex items-center justify-center text-ink-subtle/40">
                                        <flux:icon.chevron-left class="size-4" stroke-width="2" />
                                    </span>
                                @endif

                                <span class="text-xs font-medium text-ink-muted tabular-nums px-1">
                                    {{ $this->currentPageIndex }} of {{ $this->totalPages }}
                                </span>

                                @if ($this->nextPage)
                                    <a
                                        href="{{ route('project.notebooks.pages.edit', ['project' => $project, 'notebook' => $notebook, 'page' => $this->nextPage]) }}"
                                        wire:navigate
                                        aria-label="{{ __('Next page') }}"
                                        class="p-1.5 max-md:min-h-11 max-md:min-w-11 inline-flex items-center justify-center rounded-lg text-ink-muted hover:text-ink hover:bg-surface-low transition-colors"
                                    >
                                        <flux:icon.chevron-right class="size-4" stroke-width="2" />
                                    </a>
                                @else
                                    <span aria-disabled="true" class="p-1.5 inline-flex items-center justify-center text-ink-subtle/40">
                                        <flux:icon.chevron-right class="size-4" stroke-width="2" />
                                    </span>
                                @endif
                            </div>

                            <a
                                href="{{ $focusModeUrl }}"
                                class="hidden lg:inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium text-ink-muted hover:text-ink hover:bg-surface-low transition-colors"
                            >
                                <flux:icon.arrows-pointing-out class="size-3.5 shrink-0" />
                                <span>{{ __('Focus mode') }}</span>
                            </a>

                            <flux:dropdown>
                                <flux:button
                                    icon="ellipsis-horizontal"
                                    variant="ghost"
                                    size="sm"
                                    aria-label="{{ __('Page options') }}"
                                    class="text-ink-subtle hover:text-ink max-md:min-h-11 max-md:min-w-11"
                                />
                                <flux:menu>
                                    <flux:menu.item wire:click="deletePage" icon="trash" variant="danger">
                                        {{ __('Delete Page') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </header>

                    @if ($hasError && trim($content) === '' && trim($title) === '')
                        <div class="bg-danger-tint dark:bg-red-950/40 text-danger dark:text-red-300 p-4 rounded-xl mb-6 flex flex-wrap justify-between items-center gap-3 border border-ghost">
                            <span class="text-sm font-medium">{{ __('You cleared the page. We prevented silent deletion.') }}</span>
                            <flux:button type="button" variant="danger" size="sm" wire:click="deletePage">
                                {{ __('Delete Page') }}
                            </flux:button>
                        </div>
                    @endif

                    <div class="flex-1 flex flex-col max-w-[70ch] w-full min-w-0">
                        <input
                            type="text"
                            wire:model.live.debounce.1000ms="title"
                            placeholder="Optional title..."
                            aria-label="Page title"
                            x-data="{ focused: false }"
                            @focus="focused = true"
                            @blur="focused = false"
                            :class="($wire.title || focused) ? 'text-2xl sm:text-3xl mb-5 opacity-100' : 'text-xl mb-4 opacity-60 hover:opacity-100'"
                            class="w-full bg-transparent border-0 ring-0 focus:ring-0 outline-none font-headline font-bold text-ink placeholder-ink-subtle px-0 transition-all duration-200"
                        />

                        <textarea
                            x-ref="bodyInput"
                            x-show="mode === 'write'"
                            wire:model.live.debounce.1000ms="content"
                            @input="hasPendingSync = true"
                            aria-label="Page content"
                            class="w-full flex-1 min-h-[24rem] bg-transparent border-0 ring-0 focus:ring-0 resize-none outline-none text-base leading-relaxed text-ink placeholder-ink-subtle px-0"
                            placeholder="Start writing..."
                        ></textarea>

                        <div
                            x-show="mode === 'preview'"
                            x-cloak
                            style="display: none;"
                            class="flex-1 min-w-0 w-full overflow-x-hidden"
                        >
                            @if (blank($this->previewHtml->toHtml()))
                                <p class="text-base leading-relaxed text-ink-subtle italic">{{ __('Nothing to preview') }}</p>
                            @else
                                <div class="markdown-content min-w-0">{!! $this->previewHtml !!}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <footer class="pt-6 mt-8 border-t border-ghost flex flex-wrap items-center justify-between gap-4 text-xs text-ink-muted">
                    <div class="flex items-center gap-4">
                        @if ($this->previousPage)
                            <a
                                href="{{ route('project.notebooks.pages.edit', ['project' => $project, 'notebook' => $notebook, 'page' => $this->previousPage]) }}"
                                wire:navigate
                                class="text-sm font-medium text-ink-muted hover:text-ink transition-colors inline-flex items-center gap-1.5 max-md:min-h-11"
                            >
                                <flux:icon.chevron-left class="size-4 shrink-0" stroke-width="2" />
                                <span>{{ __('Previous Page') }}</span>
                            </a>
                        @endif

                        @if ($this->nextPage)
                            <a
                                href="{{ route('project.notebooks.pages.edit', ['project' => $project, 'notebook' => $notebook, 'page' => $this->nextPage]) }}"
                                wire:navigate
                                class="text-sm font-medium text-ink-muted hover:text-ink transition-colors inline-flex items-center gap-1.5 max-md:min-h-11"
                            >
                                <span>{{ __('Next Page') }}</span>
                                <flux:icon.chevron-right class="size-4 shrink-0" stroke-width="2" />
                            </a>
                        @else
                            <button
                                type="button"
                                wire:click="createNextPage"
                                class="text-sm font-medium text-ink-muted hover:text-ink transition-colors inline-flex items-center gap-1.5 cursor-pointer max-md:min-h-11"
                            >
                                <span>{{ __('New Page') }}</span>
                                <flux:icon.plus class="size-4 shrink-0" stroke-width="2" />
                            </button>
                        @endif
                    </div>

                    @if ($notebook->google_doc_synced_at)
                        <div class="flex items-center gap-2 text-xs text-ink-subtle">
                            <flux:icon.arrow-path class="size-3.5 shrink-0" />
                            <span>
                                {{ __('Synced with Google Docs') }} {{ $notebook->google_doc_synced_at->timezone($userTimezone)->locale('en')->diffForHumans() }}
                            </span>
                            @if ($notebook->hasUnsyncedChanges())
                                <span class="text-amber-700 dark:text-amber-300 font-medium">· {{ __('Unsynced changes') }}</span>
                            @endif
                        </div>
                    @endif
                </footer>
            </section>
        </div>
    @endif
</div>
