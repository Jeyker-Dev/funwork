<?php

use App\Actions\Notebooks\SyncNotebookToGoogleDocAction;
use App\Models\GoogleAccount;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function connectedOwner(): User
{
    $owner = User::factory()->create();

    GoogleAccount::create([
        'user_id' => $owner->id,
        'google_id' => 'google-123',
        'email' => 'owner@gmail.com',
        'access_token' => 'valid-access',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addHour(),
    ]);

    return $owner;
}

beforeEach(function () {
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
    ]);
});

test('the first sync creates a Google Doc with the notebook export', function () {
    Http::fake(['www.googleapis.com/upload/drive/v3/files*' => Http::response(['id' => 'doc-1'])]);

    $owner = connectedOwner();
    $project = Project::factory()->for($owner, 'owner')->create(['name' => 'Fun Work']);
    $notebook = Notebook::factory()->for($project)->create([
        'name' => 'My Notebook',
        'created_by' => $owner->id,
    ]);
    $notebook->pages()->create([
        'content' => ['title' => 'Page One', 'body' => 'Page content here'],
        'order' => 1,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->call('syncGoogleDoc', $notebook->id)
        ->assertHasNoErrors()
        ->assertSee('https://docs.google.com/document/d/doc-1/edit');

    expect($notebook->fresh())
        ->google_doc_id->toBe('doc-1')
        ->and($notebook->fresh()->google_doc_synced_at)->not->toBeNull();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->body(), '"name":"My Notebook \\u2014 Fun Work (FunWork)"')
        && str_contains($request->body(), 'Page One')
        && str_contains($request->body(), 'Page content here'));
});

test('a later sync replaces the content of the same Doc', function () {
    Http::fake(['www.googleapis.com/upload/drive/v3/files/doc-1*' => Http::response(['id' => 'doc-1', 'trashed' => false])]);

    $owner = connectedOwner();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
    ]);
    $notebook->forceFill(['google_doc_id' => 'doc-1'])->save();

    $this->actingAs($owner);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->call('syncGoogleDoc', $notebook->id);

    expect($notebook->fresh()->google_doc_id)->toBe('doc-1');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH');
});

test('a deleted Doc is created again', function () {
    Http::fake([
        'www.googleapis.com/upload/drive/v3/files/doc-1*' => Http::response(['error' => ['code' => 404]], 404),
        'www.googleapis.com/upload/drive/v3/files?*' => Http::response(['id' => 'doc-2']),
    ]);

    $owner = connectedOwner();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
    ]);
    $notebook->forceFill(['google_doc_id' => 'doc-1'])->save();

    $this->actingAs($owner);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->call('syncGoogleDoc', $notebook->id);

    expect($notebook->fresh()->google_doc_id)->toBe('doc-2');
});

test('a user without a Google connection is sent to connect and comes back', function () {
    Http::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->call('syncGoogleDoc', $notebook->id)
        ->assertRedirect(route('google.redirect'));

    expect(session('google_return_to'))->toBe(route('project.notebooks', $project, absolute: false));
    Http::assertNothingSent();
});

test('a revoked Google connection is removed and the user is sent to connect again', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    $owner = connectedOwner();
    $owner->googleAccount->update(['expires_at' => now()->subMinute()]);
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->call('syncGoogleDoc', $notebook->id)
        ->assertRedirect(route('google.redirect'));

    expect($owner->fresh()->googleAccount)->toBeNull();
});

test('a Google error keeps the stored Doc and shows an error', function () {
    Http::fake(['www.googleapis.com/upload/drive/v3/files/*' => Http::response(['error' => 'boom'], 500)]);

    $owner = connectedOwner();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
    ]);
    $notebook->forceFill(['google_doc_id' => 'doc-1'])->save();

    $this->actingAs($owner);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])
        ->call('syncGoogleDoc', $notebook->id)
        ->assertNoRedirect();

    expect($notebook->fresh())
        ->google_doc_id->toBe('doc-1')
        ->google_doc_synced_at->toBeNull();
});

test('another user cannot sync the notebook', function () {
    Http::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
    ]);

    $otherUser = connectedOwner();
    $this->actingAs($otherUser);

    Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project])->assertForbidden();

    expect(fn () => app(SyncNotebookToGoogleDocAction::class)->execute($notebook, $otherUser))
        ->toThrow(AuthorizationException::class);

    Http::assertNothingSent();
});
