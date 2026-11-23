<?php

use App\Actions\Projects\CreateDefaultAreasAction;
use App\Enums\AreaColor;
use App\Models\GoogleAccount;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('show notebooks component renders dense row with real data and no hardcoded placeholder content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);
    $backendArea = $project->areas()->where('slug', 'backend')->first();

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'area_id' => $backendArea->id,
        'name' => 'Architecture',
        'description' => 'Stack, layers, modules and conventions',
        'updated_at' => now()->subDays(5),
    ]);

    NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'created_by' => $user->id,
        'order' => 1,
        'source_path' => 'docs/architecture.md',
        'updated_at' => now()->subHours(3),
    ]);
    NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'created_by' => $user->id,
        'order' => 2,
        'updated_at' => now()->subDays(2),
    ]);

    // Ensure notebook.updated_at stays older than the most recent page updated_at
    Notebook::withoutTimestamps(function () use ($notebook): void {
        $notebook->forceFill(['updated_at' => now()->subDays(5)])->save();
    });

    $this->actingAs($user);

    $component = Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project]);

    $component->assertSee('Notebooks')
        ->assertSee('Architecture')
        ->assertSee('Stack, layers, modules and conventions')
        ->assertSee($backendArea->name)
        ->assertSeeHtml(AreaColor::dotClassFor($backendArea->color))
        ->assertSee('2 pages')
        ->assertSee('From repo docs')
        ->assertSee('Edited 3 hours ago')
        ->assertDontSee('Modified at 2h')
        ->assertDontSee('calebporzio');
});

test('show notebooks uses notebook updated_at when notebook has no pages or is newer', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Empty Notes',
        'description' => 'Freshly created',
        'updated_at' => now()->subHours(4),
    ]);

    $this->actingAs($user);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->assertSee('0 pages')
        ->assertSee('Edited 4 hours ago')
        ->assertDontSee('From repo docs');
});

test('show notebooks searches by name and description and filters by area', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);
    $backend = $project->areas()->where('slug', 'backend')->first();
    $design = $project->areas()->where('slug', 'design')->first();

    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'area_id' => $backend->id,
        'name' => 'API Reference',
        'description' => 'HTTP endpoints and auth',
    ]);
    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'area_id' => $design->id,
        'name' => 'Design Tokens',
        'description' => 'Colors and typography',
    ]);

    $this->actingAs($user);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->set('search', 'endpoints')
        ->assertSee('API Reference')
        ->assertDontSee('Design Tokens')
        ->set('search', '')
        ->call('setArea', $design->slug)
        ->assertSee('Design Tokens')
        ->assertDontSee('API Reference')
        ->set('search', 'nonexistent-query')
        ->assertSee('No matching notebooks')
        ->call('resetFilters')
        ->assertSee('API Reference')
        ->assertSee('Design Tokens');
});

test('row dropdown menu is in English and hides Open in Google Docs unless linked', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Local Only',
        'google_doc_id' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->assertSee('Export as Markdown')
        ->assertSee('Change area')
        ->assertSee('Sync with Google Docs')
        ->assertDontSee('Open in Google Docs')
        ->assertDontSee('Descargar .md')
        ->assertDontSee('Sincronizar con Google Docs')
        ->assertDontSee('Abrir Google Doc')
        ->assertDontSee('Última sincronización');
});

test('row dropdown menu shows Open in Google Docs and English last synced date when linked', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Linked Notebook',
        'google_doc_id' => 'doc-xyz',
        'google_doc_synced_at' => now()->subMinutes(15),
        'content_changed_at' => now()->subMinutes(20),
    ]);

    $this->actingAs($user);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->assertSee('Open in Google Docs')
        ->assertSee('https://docs.google.com/document/d/doc-xyz/edit')
        ->assertSee('Last synced:');
});

test('asserts empty state rendering when the project has zero notebooks on index', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.notebooks.index', ['project' => $project])
        ->assertSee('Create your first notebook')
        ->assertSee('funwork:sync-docs')
        ->assertDontSee('No Notebooks,')
        ->assertDontSee('Yet.');
});

test('the unsynced changes status shows in English with pending changes', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'google_doc_id' => 'doc-123',
        'google_doc_synced_at' => now()->subMinutes(10),
        'content_changed_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($user);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->assertSee('Unsynced changes')
        ->assertDontSee('Cambios sin sincronizar');
});

test('the unsynced changes status is hidden after a sync', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'google_doc_id' => 'doc-123',
        'google_doc_synced_at' => now(),
        'content_changed_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($user);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->assertDontSee('Unsynced changes')
        ->assertDontSee('Cambios sin sincronizar');
});

test('the unsynced changes status is hidden for a notebook without a Doc', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'google_doc_id' => null,
        'content_changed_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->assertDontSee('Unsynced changes')
        ->assertDontSee('Cambios sin sincronizar');
});

test('the unsynced changes status disappears after manual sync succeeds', function () {
    Http::fake([
        'www.googleapis.com/upload/drive/v3/files/doc-123*' => Http::response(['id' => 'doc-123', 'trashed' => false]),
    ]);

    $owner = User::factory()->create();
    GoogleAccount::create([
        'user_id' => $owner->id,
        'google_id' => 'google-123',
        'email' => 'owner@gmail.com',
        'access_token' => 'valid-access',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addHour(),
    ]);

    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
        'google_doc_id' => 'doc-123',
        'google_doc_synced_at' => now()->subMinutes(10),
        'content_changed_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($owner);

    $component = Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project]);
    $component->assertSee('Unsynced changes');

    $component->call('syncGoogleDoc', $notebook->id);

    $component->assertDontSee('Unsynced changes');
});

test('show notebooks renders without N+1 queries as notebook count grows', function () {
    $user = User::factory()->create();
    $projectOne = Project::factory()->create(['owner_id' => $user->id]);
    $projectMany = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($projectOne);
    app(CreateDefaultAreasAction::class)($projectMany);

    $areaOne = $projectOne->areas()->first();
    $areaMany = $projectMany->areas()->first();

    $nb1 = Notebook::factory()->create([
        'project_id' => $projectOne->id,
        'created_by' => $user->id,
        'area_id' => $areaOne->id,
    ]);
    NotebookPage::factory()->create([
        'notebook_id' => $nb1->id,
        'created_by' => $user->id,
        'order' => 1,
        'source_path' => 'docs/readme.md',
    ]);
    NotebookPage::factory()->create([
        'notebook_id' => $nb1->id,
        'created_by' => $user->id,
        'order' => 2,
    ]);

    for ($i = 1; $i <= 10; $i++) {
        $nb = Notebook::factory()->create([
            'project_id' => $projectMany->id,
            'created_by' => $user->id,
            'area_id' => $areaMany->id,
            'name' => "Notebook {$i}",
        ]);
        NotebookPage::factory()->create([
            'notebook_id' => $nb->id,
            'created_by' => $user->id,
            'order' => 1,
            'source_path' => "docs/file-{$i}.md",
        ]);
        NotebookPage::factory()->count(2)->create([
            'notebook_id' => $nb->id,
            'created_by' => $user->id,
        ]);
    }

    $this->actingAs($user);

    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $projectOne]);
    $queriesForOne = count(DB::getQueryLog());

    DB::flushQueryLog();
    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $projectMany]);
    $queriesForTen = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queriesForTen)->toBe($queriesForOne);
});
