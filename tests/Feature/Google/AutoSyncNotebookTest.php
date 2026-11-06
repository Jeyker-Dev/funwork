<?php

use App\Actions\Notebooks\SyncNotebookToGoogleDocAction;
use App\Jobs\SyncNotebookToGoogleDocJob;
use App\Models\GoogleAccount;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function createConnectedOwner(): User
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

test('saving a page of a notebook with a Doc dispatches the job delayed and once per notebook', function () {
    Queue::fake();

    $owner = createConnectedOwner();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
        'google_doc_id' => 'doc-123',
    ]);

    $page = NotebookPage::create([
        'notebook_id' => $notebook->id,
        'content' => ['title' => 'Page 1', 'body' => 'Initial content'],
        'order' => 1,
        'created_by' => $owner->id,
    ]);

    expect($notebook->fresh()->content_changed_at)->not->toBeNull();

    Queue::assertPushed(SyncNotebookToGoogleDocJob::class, function ($job) use ($notebook) {
        return $job->uniqueId() === (string) $notebook->id && $job->delay !== null;
    });

    // Second save on the page
    $page->update(['content' => ['title' => 'Page 1 updated', 'body' => 'More content']]);

    // Unique job ensures only 1 pushed
    Queue::assertPushed(SyncNotebookToGoogleDocJob::class, 1);
});

test('saving a page of a notebook without a Doc dispatches nothing but sets content_changed_at', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
        'google_doc_id' => null,
    ]);

    NotebookPage::create([
        'notebook_id' => $notebook->id,
        'content' => ['title' => 'Page 1', 'body' => 'Initial content'],
        'order' => 1,
        'created_by' => $owner->id,
    ]);

    expect($notebook->fresh()->content_changed_at)->not->toBeNull();
    Queue::assertNothingPushed();
});

test('deleting a page sets content_changed_at', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
        'google_doc_id' => null,
    ]);

    $page = NotebookPage::withoutEvents(function () use ($notebook, $owner) {
        return NotebookPage::create([
            'notebook_id' => $notebook->id,
            'content' => ['title' => 'Page 1', 'body' => 'Initial content'],
            'order' => 1,
            'created_by' => $owner->id,
        ]);
    });

    expect($notebook->fresh()->content_changed_at)->toBeNull();

    $page->delete();

    expect($notebook->fresh()->content_changed_at)->not->toBeNull();
});

test('SyncDocsCommand dispatches the job once for the Docs notebook when it has a Doc and something changed', function () {
    $tempDir = sys_get_temp_dir().'/funwork_test_sync_job_'.uniqid('', true);
    mkdir($tempDir, 0777, true);
    file_put_contents($tempDir.'/doc.md', '# Initial Docs');

    $owner = createConnectedOwner();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'name' => 'Docs',
        'created_by' => $owner->id,
        'google_doc_id' => 'doc-docs-123',
    ]);

    Queue::fake();

    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $tempDir,
    ])->assertSuccessful();

    expect($notebook->fresh()->content_changed_at)->not->toBeNull();
    Queue::assertPushed(SyncNotebookToGoogleDocJob::class, 1);

    File::deleteDirectory($tempDir);
});

test('SyncDocsCommand does not dispatch when nothing changed', function () {
    $tempDir = sys_get_temp_dir().'/funwork_test_sync_job_'.uniqid('', true);
    mkdir($tempDir, 0777, true);
    file_put_contents($tempDir.'/doc.md', '# Initial Docs');

    $owner = createConnectedOwner();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'name' => 'Docs',
        'created_by' => $owner->id,
        'google_doc_id' => 'doc-docs-123',
    ]);

    Queue::fake();

    // First run creates the doc and pushes 1 job
    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $tempDir,
    ])->assertSuccessful();

    Queue::assertPushed(SyncNotebookToGoogleDocJob::class, 1);

    // Second run has no changes, so count remains 1
    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $tempDir,
    ])->assertSuccessful();

    Queue::assertPushed(SyncNotebookToGoogleDocJob::class, 1);

    File::deleteDirectory($tempDir);
});

test('the job syncs when there are unsynced changes', function () {
    Http::fake([
        'www.googleapis.com/upload/drive/v3/files/doc-123*' => Http::response(['id' => 'doc-123', 'trashed' => false]),
    ]);

    $owner = createConnectedOwner();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
        'google_doc_id' => 'doc-123',
        'google_doc_synced_at' => now()->subMinutes(10),
        'content_changed_at' => now()->subMinutes(5),
    ]);

    $job = new SyncNotebookToGoogleDocJob($notebook->id);
    $job->handle(app(SyncNotebookToGoogleDocAction::class));

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH');

    expect($notebook->fresh()->google_doc_synced_at)->not->toBeNull()
        ->and($notebook->fresh()->google_doc_synced_at->gte($notebook->fresh()->content_changed_at))->toBeTrue();
});

test('the job skips when already synced', function () {
    Http::fake();

    $owner = createConnectedOwner();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
        'google_doc_id' => 'doc-123',
        'google_doc_synced_at' => now(),
        'content_changed_at' => now()->subMinutes(5),
    ]);

    $job = new SyncNotebookToGoogleDocJob($notebook->id);
    $job->handle(app(SyncNotebookToGoogleDocAction::class));

    Http::assertNothingSent();
});

test('the job does nothing when google_doc_id is null', function () {
    Http::fake();

    $owner = createConnectedOwner();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
        'google_doc_id' => null,
        'content_changed_at' => now(),
    ]);

    $job = new SyncNotebookToGoogleDocJob($notebook->id);
    $job->handle(app(SyncNotebookToGoogleDocAction::class));

    Http::assertNothingSent();
});

test('when the token is revoked the job does not retry and the Google account is deleted', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $owner = createConnectedOwner();
    $owner->googleAccount->update(['expires_at' => now()->subMinute()]);
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
        'google_doc_id' => 'doc-123',
        'content_changed_at' => now(),
    ]);

    $job = new SyncNotebookToGoogleDocJob($notebook->id);
    $job->handle(app(SyncNotebookToGoogleDocAction::class));

    expect($owner->fresh()->googleAccount)->toBeNull();
});
