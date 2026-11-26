<?php

use App\Actions\Notebooks\SyncDocsNotebookAction;
use App\Contracts\RepositoryDocsSource;
use App\Data\RepositoryConnections\RepositoryDocsSnapshot;
use App\Jobs\SyncRepositoryDocsJob;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function signedPushHeaders(RepositoryConnection $connection, string $body): array
{
    return [
        'X-GitHub-Event' => 'push',
        'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $connection->webhook_secret),
        'Content-Type' => 'application/json',
    ];
}

function fakeDocsSource(string $headSha): void
{
    app()->instance(RepositoryDocsSource::class, new class($headSha) implements RepositoryDocsSource
    {
        public function __construct(private string $headSha) {}

        public function headCommit(RepositoryConnection $connection): string
        {
            return $this->headSha;
        }

        public function fetchDocs(RepositoryConnection $connection, string $commitSha): RepositoryDocsSnapshot
        {
            return new RepositoryDocsSnapshot($commitSha, ['docs/readme.md' => '# Readme'], []);
        }
    });
}

test('creating a connection from settings queues the first sync and shows it as syncing', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    Livewire::actingAs($owner)
        ->test('projects.repository-connections', ['project' => $project])
        ->call('openCreate')
        ->set('repository', 'acme/website')
        ->set('branch', 'main')
        ->set('docsPath', 'docs')
        ->set('token', 'github_pat_first_sync_1234')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Syncing')
        ->assertSeeHtml('wire:poll');

    $connection = RepositoryConnection::query()->where('project_id', $project->id)->firstOrFail();

    expect($connection->sync_requested_at)->not->toBeNull();
    Queue::assertPushed(SyncRepositoryDocsJob::class, fn (SyncRepositoryDocsJob $job): bool => $job->repositoryConnection->is($connection));
});

test('sync now marks the connection as syncing', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->create(['project_id' => $project->id]);

    Livewire::actingAs($owner)
        ->test('projects.repository-connections', ['project' => $project])
        ->call('syncNow', $connection->id)
        ->assertSee('Syncing')
        ->assertSeeHtml('wire:poll');

    expect($connection->fresh()->sync_requested_at)->not->toBeNull();
    Queue::assertPushed(SyncRepositoryDocsJob::class);
});

test('a webhook push marks the connection as syncing', function () {
    Queue::fake();

    $connection = RepositoryConnection::factory()->create(['branch' => 'main', 'docs_path' => 'docs']);
    $body = json_encode([
        'ref' => 'refs/heads/main',
        'commits' => [['added' => ['docs/new.md'], 'modified' => [], 'removed' => []]],
    ]);

    $this->call('POST', route('webhooks.github.repository-connections', $connection), [], [], [], $this->transformHeadersToServerVars(signedPushHeaders($connection, $body)), $body)
        ->assertAccepted();

    expect($connection->fresh()->sync_requested_at)->not->toBeNull();
});

test('the card does not poll when no sync is pending', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    RepositoryConnection::factory()->create(['project_id' => $project->id, 'sync_requested_at' => null]);

    Livewire::actingAs($owner)
        ->test('projects.repository-connections', ['project' => $project])
        ->assertDontSee('Syncing')
        ->assertDontSeeHtml('wire:poll');
});

test('a sync request older than the pending window is no longer shown as syncing', function () {
    $connection = RepositoryConnection::factory()->create([
        'sync_requested_at' => now()->subMinutes(RepositoryConnection::SYNC_PENDING_MINUTES + 1),
    ]);

    expect($connection->isSyncing())->toBeFalse();
});

test('a successful sync clears the pending request', function () {
    fakeDocsSource('abc123');
    $connection = RepositoryConnection::factory()->create(['sync_requested_at' => now()]);

    (new SyncRepositoryDocsJob($connection))->handle(app(RepositoryDocsSource::class), app(SyncDocsNotebookAction::class));

    expect($connection->fresh()->sync_requested_at)->toBeNull()
        ->and($connection->fresh()->last_synced_sha)->toBe('abc123');
});

test('a skipped sync for an already synced commit clears the pending request', function () {
    fakeDocsSource('abc123');
    $connection = RepositoryConnection::factory()->create([
        'last_synced_sha' => 'abc123',
        'last_synced_at' => now(),
        'sync_requested_at' => now(),
    ]);

    (new SyncRepositoryDocsJob($connection))->handle(app(RepositoryDocsSource::class), app(SyncDocsNotebookAction::class));

    expect($connection->fresh()->sync_requested_at)->toBeNull();
});

test('marking a connection broken or recording an error clears the pending request', function () {
    $broken = RepositoryConnection::factory()->create(['sync_requested_at' => now()]);
    $broken->markBroken('GitHub rejected the token (HTTP 401).');

    $errored = RepositoryConnection::factory()->create(['sync_requested_at' => now()]);
    $errored->recordError('Docs sync failed unexpectedly.');

    expect($broken->fresh()->sync_requested_at)->toBeNull()
        ->and($errored->fresh()->sync_requested_at)->toBeNull();
});
