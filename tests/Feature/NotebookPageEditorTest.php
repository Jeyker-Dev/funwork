<?php

use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const NOTEBOOK_EDITOR_SQL_UPDATE = 'update';

function mountNotebookPageEditor(User $user, Project $project, Notebook $notebook, NotebookPage $page): Testable
{
    return Livewire::actingAs($user)->test('pages::projects.notebooks.pages.editor', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page,
    ]);
}

function failNextNotebookPageUpdate(string $exceptionMessage): void
{
    $tableName = (new NotebookPage)->getTable();
    $armed = true;

    DB::beforeExecuting(function (string $query) use (&$armed, $tableName, $exceptionMessage): void {
        if (! $armed) {
            return;
        }

        if (str_contains($query, $tableName) && str_starts_with(strtolower($query), NOTEBOOK_EDITOR_SQL_UPDATE)) {
            $armed = false;

            throw new RuntimeException($exceptionMessage);
        }
    });
}

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['owner_id' => $this->user->id]);
    $this->notebook = Notebook::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
    ]);
    $this->page = NotebookPage::factory()->create([
        'notebook_id' => $this->notebook->id,
        'created_by' => $this->user->id,
        'order' => 1,
        'content' => [
            'title' => 'Initial title',
            'body' => 'Initial content',
        ],
    ]);
});

test('it binds notebook page and allows autosaving content', function (): void {
    mountNotebookPageEditor($this->user, $this->project, $this->notebook, $this->page)
        ->assertSet('content', 'Initial content')
        ->set('content', 'Updated content')
        ->assertHasNoErrors();

    expect($this->page->refresh()->content['body'])->toBe('Updated content');
});

test('it recovers from save failures', function (): void {
    Log::spy();
    failNextNotebookPageUpdate('Temporary DB glitch');

    $component = mountNotebookPageEditor($this->user, $this->project, $this->notebook, $this->page)
        ->set('content', 'Recovered content')
        ->assertSet('hasError', true)
        ->assertSet('errorMessage', 'Failed to save.')
        ->assertSee('Retry');

    expect($this->page->refresh()->content['body'])->toBe('Initial content');

    $component->call('retrySave')
        ->assertSet('hasError', false)
        ->assertSee('Autosaved');

    expect($this->page->refresh()->content['body'])->toBe('Recovered content');
});

test('it prevents silent deletion and protects empty pages', function (): void {
    NotebookPage::factory()->create([
        'notebook_id' => $this->notebook->id,
        'created_by' => $this->user->id,
        'order' => 2,
    ]);

    mountNotebookPageEditor($this->user, $this->project, $this->notebook, $this->page)
        ->set('title', '')
        ->set('content', '')
        ->assertSet('hasError', true)
        ->assertSet('errorMessage', 'Page cannot be empty. Delete explicitly if needed.');

    expect(NotebookPage::find($this->page->id))->not->toBeNull();
});

test('it creates the next page seamlessly', function (): void {
    mountNotebookPageEditor($this->user, $this->project, $this->notebook, $this->page)
        ->call('createNextPage')
        ->assertRedirect();

    expect($this->notebook->pages()->count())->toBe(2)
        ->and($this->notebook->pages()->max('order'))->toBe(2);
});

test('it deletes page and redirects to fallback page when multiple pages exist', function (): void {
    $page2 = NotebookPage::factory()->create([
        'notebook_id' => $this->notebook->id,
        'created_by' => $this->user->id,
        'order' => 2,
    ]);

    mountNotebookPageEditor($this->user, $this->project, $this->notebook, $page2)
        ->call('deletePage')
        ->assertRedirect(route('project.notebooks.pages.edit', [
            'project' => $this->project,
            'notebook' => $this->notebook,
            'page' => $this->page,
        ]));

    expect(NotebookPage::find($page2->id))->toBeNull()
        ->and(NotebookPage::count())->toBe(1);
});

test('it clears content instead of deleting when deleting the only page', function (): void {
    mountNotebookPageEditor($this->user, $this->project, $this->notebook, $this->page)
        ->call('deletePage')
        ->assertNoRedirect()
        ->assertSet('title', '')
        ->assertSet('content', '');

    expect(NotebookPage::find($this->page->id))->not->toBeNull()
        ->and($this->page->fresh()->content)->toBe(['title' => '', 'body' => '']);
});

test('it handles unexpected error during save by setting error message and logging with context', function (): void {
    Log::spy();
    failNextNotebookPageUpdate('Unexpected DB glitch');

    mountNotebookPageEditor($this->user, $this->project, $this->notebook, $this->page)
        ->set('content', 'Changed content')
        ->assertSet('hasError', true)
        ->assertSet('errorMessage', 'Failed to save.');

    Log::shouldHaveReceived('error')
        ->with('Autosave failed: Unexpected DB glitch', Mockery::on(function (array $context): bool {
            return ($context['page_id'] ?? null) === $this->page->id
                && isset($context['exception']);
        }));
});

test('it renders markdown preview html for headings, lists, and code', function (): void {
    $markdown = <<<'MD'
    # Architecture Notes

    - First item
    - Second item with `inline()`

    ```php
    echo 'hello';
    ```
    MD;

    mountNotebookPageEditor($this->user, $this->project, $this->notebook, $this->page)
        ->assertSee('Write')
        ->assertSee('Preview')
        ->set('content', $markdown)
        ->assertHasNoErrors()
        ->assertSeeHtml('class="markdown-content')
        ->assertSeeHtml('<h1>Architecture Notes</h1>')
        ->assertSeeHtml('<ul>')
        ->assertSeeHtml('<li>First item</li>')
        ->assertSeeHtml('<code>inline()</code>')
        ->assertSeeHtml('<pre><code');
});

test('it stores raw markdown unchanged in body when editing', function (): void {
    $markdown = <<<'MD'
    # Raw Markdown

    - Preserved **verbatim** with `code`
    MD;

    mountNotebookPageEditor($this->user, $this->project, $this->notebook, $this->page)
        ->set('content', $markdown)
        ->assertHasNoErrors();

    expect($this->page->refresh()->content)->toBe([
        'title' => 'Initial title',
        'body' => $markdown,
    ]);
});

test('it strips script tags, javascript links, and html attributes from markdown preview', function (): void {
    $unsafeMarkdown = <<<'MD'
    <script>alert('xss')</script>

    [Click me](javascript:alert(1)) and [Valid](https://example.com)

    Paragraph <span onclick="alert(2)">Safe text</span> <img src="x" onerror="alert(3)">
    MD;

    $component = mountNotebookPageEditor($this->user, $this->project, $this->notebook, $this->page)
        ->set('content', $unsafeMarkdown)
        ->assertDontSeeHtml('<script>')
        ->assertDontSeeHtml('</script>')
        ->assertDontSeeHtml('href="javascript:')
        ->assertDontSeeHtml('onclick="')
        ->assertDontSeeHtml('onerror="')
        ->assertDontSeeHtml('<img')
        ->assertSeeHtml('href="https://example.com"')
        ->assertSee('Safe text');

    $previewHtml = $component->get('previewHtml')->toHtml();

    expect($previewHtml)
        ->not->toContain('<script>')
        ->not->toContain('javascript:')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('<img')
        ->toContain('Safe text');
});

test('it shows muted empty state in preview when body is blank', function (): void {
    $emptyBodyPage = NotebookPage::factory()->create([
        'notebook_id' => $this->notebook->id,
        'created_by' => $this->user->id,
        'order' => 2,
        'content' => ['title' => 'Title only', 'body' => ''],
    ]);

    mountNotebookPageEditor($this->user, $this->project, $this->notebook, $emptyBodyPage)
        ->assertSee('Nothing to preview')
        ->assertDontSeeHtml('class="markdown-content');
});
