<?php

use App\Models\User;
use App\Models\Project;
use App\Models\Notebook;
use App\Models\NotebookPage;
use Livewire\Livewire;
use Illuminate\Support\Facades\Log;

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

    Livewire::test('pages::projects.notebooks.pages.editor', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page,
    ])
    ->assertSet('content', 'Initial content')
    ->set('content', 'Updated content')
    ->assertHasNoErrors();

    $this->assertEquals('Updated content', $page->refresh()->content);
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

    $component = Livewire::test('pages::projects.notebooks.pages.editor', [
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
        ->assertSet('hasError', false)
        ->assertSet('errorMessage', '');
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

    $this->actingAs($user);

    Livewire::test('pages::projects.notebooks.pages.editor', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page,
    ])
    ->set('content', '')
    ->assertSet('hasError', true)
    ->assertSet('errorMessage', 'Page content cannot be empty. Delete explicitly if needed.');

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

    Livewire::test('pages::projects.notebooks.pages.editor', [
        'project' => $project,
        'notebook' => $notebook,
        'page' => $page,
    ])
    ->call('createNextPage')
    ->assertRedirect();

    $this->assertCount(2, $notebook->pages);
    $this->assertEquals(2, $notebook->pages()->max('order'));
});
