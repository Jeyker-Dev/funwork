<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Project;
use App\Models\Notebook;
use App\Models\NotebookPage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.focus')]
class extends Component {
    public Project $project;
    public Notebook $notebook;
    public NotebookPage $page;

    public string $title = '';
    public string $content = '';

    public bool $hasError = false;
    public string $errorMessage = '';

    public function mount(Project $project, Notebook $notebook, NotebookPage $page): void
    {
        $this->project = $project;
        $this->notebook = $notebook;
        $this->page = $page;

        $this->title = is_array($page->content) ? ($page->content['title'] ?? '') : '';
        $this->content = is_array($page->content) ? ($page->content['body'] ?? '') : (is_string($page->content) ? $page->content : '');
    }

    #[Computed]
    public function previousPage(): ?NotebookPage
    {
        return $this->notebook->pages()->where('order', '<', $this->page->order)->orderBy('order', 'desc')->first();
    }

    #[Computed]
    public function nextPage(): ?NotebookPage
    {
        return $this->notebook->pages()->where('order', '>', $this->page->order)->orderBy('order', 'asc')->first();
    }

    #[Computed]
    public function totalPages(): int
    {
        return $this->notebook->pages()->count();
    }

    #[Computed]
    public function currentPageIndex(): int
    {
        return $this->notebook->pages()->where('order', '<=', $this->page->order)->count();
    }

    public function updatedTitle(): void
    {
        $this->save();
    }

    public function updatedContent(): void
    {
        $this->save();
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $this->hasError = false;

        if (trim($this->content) === '' && trim($this->title) === '') {
            if ($this->totalPages > 1) {
                $this->hasError = true;
                $this->errorMessage = 'Page cannot be empty. Delete explicitly if needed.';
                return;
            }
        }

        try {
            $this->page->update([
                'content' => [
                    'title' => $this->title,
                    'body' => $this->content,
                ],
            ]);
        } catch (\Exception $e) {
            $this->hasError = true;
            $this->errorMessage = 'Failed to save.';
            Log::error('Autosave failed: ' . $e->getMessage());
        }
    }

    public function retrySave(): void
    {
        $this->save();
    }

    public function deletePage(): ?\Illuminate\Http\RedirectResponse
    {
        if ($this->totalPages <= 1) {
            $this->title = '';
            $this->content = '';
            $this->save();
            return null;
        }

        $fallbackPage = $this->previousPage ?? $this->nextPage;

        $this->page->delete();

        if ($fallbackPage) {
            return redirect()->route('project.notebooks.pages.edit', [
                'project' => $this->project,
                'notebook' => $this->notebook,
                'page' => $fallbackPage,
            ]);
        }

        return redirect()->route('project.notebooks', ['project' => $this->project]);
    }

    public function createNextPage(): \Illuminate\Http\RedirectResponse
    {
        $nextOrder = $this->notebook->pages()->max('order') + 1;
        $newPage = $this->notebook->pages()->create([
            'content' => ['title' => '', 'body' => ''],
            'order' => $nextOrder,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('project.notebooks.pages.edit', [
            'project' => $this->project,
            'notebook' => $this->notebook,
            'page' => $newPage,
        ]);
    }
};
?>

<div class="flex flex-col h-screen w-full max-w-5xl mx-auto px-6 pt-8 pb-4" x-data="{
    handleKeydown(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            @this.createNextPage();
        }
    }
}" @keydown.window="handleKeydown">

    <!-- Header -->
    <div class="flex justify-between items-center mb-16">
        <!-- Left: Back -->
        <div class="flex-1">
            <a href="{{ route('project.notebooks', ['project' => $project]) }}"
               class="text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 transition flex items-center gap-2"
               wire:navigate>
                <flux:icon.arrow-left class="size-4" stroke-width="2.5"/>
                Back to Notebook
            </a>
        </div>

        <!-- Center: Document Name -->
        <div
            class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400 uppercase tracking-widest text-xs font-bold">
            <flux:icon.document-text class="size-4"/>
            <span>{{ $notebook->name }}</span>
        </div>

        <!-- Right: Autosaved & Menu -->
        <div class="flex-1 flex justify-end items-center gap-4">
            <div
                class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 transition-opacity duration-300"
                wire:loading.class="opacity-50" wire:target="title, content, save">
                @if($hasError)
                    <flux:icon.exclamation-circle class="size-4 text-red-500"/>
                    <span class="text-red-600">{{ $errorMessage }}</span>
                @else
                    <flux:icon.cloud class="size-4 text-orange-600 dark:text-orange-500"/>
                    <span>Autosaved</span>
                @endif
            </div>

            <flux:dropdown>
                <flux:button icon="ellipsis-vertical" variant="ghost" size="sm"
                             class="text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"/>
                <flux:menu>
                    <flux:menu.item wire:click="deletePage" icon="trash"
                                    class="text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30">Delete Page
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    @if($hasError && trim($content) === '' && trim($title) === '')
        <div
            class="bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-4 rounded-xl mb-6 flex justify-between items-center border border-red-200 dark:border-red-900/50 max-w-3xl mx-auto w-full">
            <span>You cleared the page. We prevented silent deletion.</span>
            <button wire:click="deletePage"
                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition shadow-sm font-medium">
                Delete Page
            </button>
        </div>
    @endif

    <!-- Canvas (Editor) -->
    <div class="flex-grow flex flex-col w-full max-w-3xl mx-auto">
        <input type="text"
               wire:model.live.debounce.1000ms="title"
               placeholder="Optional title..."
               x-data="{ focused: false }"
               @focus="focused = true"
               @blur="focused = false"
               :class="($wire.title || focused) ? 'text-4xl md:text-5xl mb-8 opacity-100' : 'text-xl mb-4 opacity-40 hover:opacity-100'"
               class="w-full bg-transparent border-0 ring-0 focus:ring-0 outline-none font-bold text-zinc-900 dark:text-zinc-100 placeholder-zinc-300 dark:placeholder-zinc-700 px-0 transition-all duration-300"/>

        <textarea
            wire:model.live.debounce.1000ms="content"
            class="w-full flex-grow bg-transparent border-0 ring-0 focus:ring-0 resize-none outline-none text-lg leading-relaxed text-zinc-800 dark:text-zinc-200 placeholder-zinc-300 dark:placeholder-zinc-700 px-0"
            placeholder="Start writing..."
        ></textarea>
    </div>

    <!-- Footer Navigation -->
    <div class="flex items-center justify-between py-6 max-w-3xl mx-auto w-full mt-auto">
        <!-- Prev -->
        <div class="flex-1">
            @if($this->previousPage)
                <a href="{{ route('project.notebooks.pages.edit', ['project' => $project, 'notebook' => $notebook, 'page' => $this->previousPage]) }}"
                   wire:navigate
                   class="text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 transition flex items-center gap-2">
                    <flux:icon.chevron-left class="size-4" stroke-width="2.5"/>
                    Previous Page
                </a>
            @endif
        </div>

        <!-- Center -->
        <div class="text-xs font-bold tracking-widest text-zinc-400 dark:text-zinc-600 uppercase">
            Page {{ $this->currentPageIndex }} of {{ $this->totalPages }}
        </div>

        <!-- Next -->
        <div class="flex-1 flex justify-end">
            @if($this->nextPage)
                <a href="{{ route('project.notebooks.pages.edit', ['project' => $project, 'notebook' => $notebook, 'page' => $this->nextPage]) }}"
                   wire:navigate
                   class="text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 transition flex items-center gap-2">
                    Next Page
                    <flux:icon.chevron-right class="size-4" stroke-width="2.5"/>
                </a>
            @else
                <button wire:click="createNextPage"
                        class="text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 transition flex items-center gap-2 group">
                    New Page
                    <flux:icon.plus class="size-4 group-hover:scale-110 transition-transform" stroke-width="2.5"/>
                </button>
            @endif
        </div>
    </div>
</div>
