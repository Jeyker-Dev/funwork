<?php

use App\Actions\Notebooks\SaveNotebookPageAction;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

test('it binds notebook page and allows autosaving content', function () {
    $user = User::factory()->create();
    $project = Project::forceCreate(['owner_id' => $user->id, 'name' => 'Test Project', 'type' => 'personal', 'status' => 'active']);
    $notebook = Notebook::forceCreate(['project_id' => $project->id, 'name' => 'Test Notebook', 'created_by' => $user->id]);
    $page = NotebookPage::forceCreate([
        'notebook_id' => $notebook->id,
        'content' => 'Initial content',
        'order' => 1,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::actingAs($user)->test('pages::projects.notebooks.pages.editor', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page,
    ])
        ->assertSet('content', 'Initial content')
        ->set('content', 'Updated content')
        ->assertHasNoErrors();

    $this->assertEquals('Updated content', $page->refresh()->content['body'] ?? $page->content);
});

test('it recovers from save failures', function () {
    $user = User::factory()->create();
    $project = Project::forceCreate(['owner_id' => $user->id, 'name' => 'Test Project', 'type' => 'personal', 'status' => 'active']);
    $notebook = Notebook::forceCreate(['project_id' => $project->id, 'name' => 'Test Notebook', 'created_by' => $user->id]);
    $page = NotebookPage::forceCreate([
        'notebook_id' => $notebook->id,
        'content' => 'Initial content',
        'order' => 1,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    $component = Livewire::actingAs($user)->test('pages::projects.notebooks.pages.editor', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page,
    ]);

    // Test that component handles errors by directly setting the error state
    $component->set('hasError', true)
        ->set('errorMessage', 'Failed to save changes.')
        ->assertSet('hasError', true)
        ->assertSee('Failed to save changes.')
        ->assertSee('Retry');

    // Now call retrySave
    $component->call('retrySave')
        ->assertSet('hasError', false);
});

test('it prevents silent deletion and protects empty pages', function () {
    $user = User::factory()->create();
    $project = Project::forceCreate(['owner_id' => $user->id, 'name' => 'Test Project', 'type' => 'personal', 'status' => 'active']);
    $notebook = Notebook::forceCreate(['project_id' => $project->id, 'name' => 'Test Notebook', 'created_by' => $user->id]);
    $page = NotebookPage::forceCreate([
        'notebook_id' => $notebook->id,
        'content' => 'Initial content',
        'order' => 1,
        'created_by' => $user->id,
    ]);

    NotebookPage::forceCreate([
        'notebook_id' => $notebook->id,
        'content' => 'Another page',
        'order' => 2,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::actingAs($user)->test('pages::projects.notebooks.pages.editor', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page,
    ])
        ->set('content', '')
        ->assertSet('hasError', true)
        ->assertSet('errorMessage', 'Page cannot be empty. Delete explicitly if needed.');

    $this->assertNotNull(NotebookPage::find($page->id));
});

test('it creates the next page seamlessly', function () {
    $user = User::factory()->create();
    $project = Project::forceCreate(['owner_id' => $user->id, 'name' => 'Test Project', 'type' => 'personal', 'status' => 'active']);
    $notebook = Notebook::forceCreate(['project_id' => $project->id, 'name' => 'Test Notebook', 'created_by' => $user->id]);
    $page = NotebookPage::forceCreate([
        'notebook_id' => $notebook->id,
        'content' => 'Initial content',
        'order' => 1,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::actingAs($user)->test('pages::projects.notebooks.pages.editor', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page,
    ])
        ->call('createNextPage')
        ->assertRedirect();

    $this->assertCount(2, $notebook->pages);
    $this->assertEquals(2, $notebook->pages()->max('order'));
});

test('it deletes page and redirects to fallback page when multiple pages exist', function () {
    $user = User::factory()->create();
    $project = Project::forceCreate(['owner_id' => $user->id, 'name' => 'Test Project', 'type' => 'personal', 'status' => 'active']);
    $notebook = Notebook::forceCreate(['project_id' => $project->id, 'name' => 'Test Notebook', 'created_by' => $user->id]);
    $page1 = NotebookPage::forceCreate([
        'notebook_id' => $notebook->id,
        'content' => 'Page 1',
        'order' => 1,
        'created_by' => $user->id,
    ]);
    $page2 = NotebookPage::forceCreate([
        'notebook_id' => $notebook->id,
        'content' => 'Page 2',
        'order' => 2,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::actingAs($user)->test('pages::projects.notebooks.pages.editor', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page2,
    ])
        ->call('deletePage')
        ->assertRedirect(route('project.notebooks.pages.edit', [
            'project' => $project,
            'notebook' => $notebook,
            'page' => $page1,
        ]));

    expect(NotebookPage::find($page2->id))->toBeNull()
        ->and(NotebookPage::count())->toBe(1);
});

test('it clears content instead of deleting when deleting the only page', function () {
    $user = User::factory()->create();
    $project = Project::forceCreate(['owner_id' => $user->id, 'name' => 'Test Project', 'type' => 'personal', 'status' => 'active']);
    $notebook = Notebook::forceCreate(['project_id' => $project->id, 'name' => 'Test Notebook', 'created_by' => $user->id]);
    $page = NotebookPage::forceCreate([
        'notebook_id' => $notebook->id,
        'content' => ['title' => 'Only Page', 'body' => 'Body'],
        'order' => 1,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::actingAs($user)->test('pages::projects.notebooks.pages.editor', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page,
    ])
        ->call('deletePage')
        ->assertNoRedirect()
        ->assertSet('title', '')
        ->assertSet('content', '');

    expect(NotebookPage::find($page->id))->not->toBeNull()
        ->and($page->fresh()->content)->toBe(['title' => '', 'body' => '']);
});

test('it handles unexpected error during save by setting error message and logging with context', function () {
    Log::spy();

    $user = User::factory()->create();
    $project = Project::forceCreate(['owner_id' => $user->id, 'name' => 'Test Project', 'type' => 'personal', 'status' => 'active']);
    $notebook = Notebook::forceCreate(['project_id' => $project->id, 'name' => 'Test Notebook', 'created_by' => $user->id]);
    $page = NotebookPage::forceCreate([
        'notebook_id' => $notebook->id,
        'content' => 'Initial content',
        'order' => 1,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    $mockAction = Mockery::mock(SaveNotebookPageAction::class);
    $mockAction->shouldReceive('execute')
        ->andThrow(new RuntimeException('Unexpected DB glitch'));
    app()->instance(SaveNotebookPageAction::class, $mockAction);

    Livewire::actingAs($user)->test('pages::projects.notebooks.pages.editor', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page,
    ])
        ->call('save')
        ->assertSet('hasError', true)
        ->assertSet('errorMessage', 'Failed to save.');

    Log::shouldHaveReceived('error')
        ->with('Autosave failed: Unexpected DB glitch', Mockery::on(function ($context) use ($page) {
            return ($context['page_id'] ?? null) === $page->id
                && isset($context['exception']);
        }));
});
