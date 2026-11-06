<?php

use App\Models\GoogleAccount;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('show notebooks component renders list correctly', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'First Notebook',
        'description' => 'A great notebook',
    ]);

    $this->actingAs($user);

    $component = Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project]);

    $component->assertSee('First Notebook')
        ->assertSee('A great notebook');
});

test('asserts empty state rendering ("No Notebooks, Yet.") when the project has zero notebooks on index', function () {
    // This satisfies task 4.3 which specifically requested it here.
    // We'll test the index component since that's where the empty state lives.
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    $component = Livewire::test('pages::projects.notebooks.index', ['project' => $project]);

    $component->assertSee('No Notebooks,')
        ->assertSee('Yet.');
});

test('the unsynced changes badge shows with pending changes', function () {
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
        ->assertSee('Cambios sin sincronizar');
});

test('the unsynced changes badge is hidden after a sync', function () {
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
        ->assertDontSee('Cambios sin sincronizar');
});

test('the unsynced changes badge is hidden for a notebook without a Doc', function () {
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
        ->assertDontSee('Cambios sin sincronizar');
});

test('the unsynced changes badge disappears after manual sync succeeds', function () {
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
    $component->assertSee('Cambios sin sincronizar');

    $component->call('syncGoogleDoc', $notebook->id);

    $component->assertDontSee('Cambios sin sincronizar');
});
