<?php

use App\Exceptions\RepositoryConnections\RepositoryAccessDeniedException;
use App\Jobs\SyncRepositoryDocsJob;
use App\Models\Area;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Services\GitHub\GitHubTokenDocsSource;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('implements ShouldQueue and ShouldBeUniqueUntilProcessing keyed by connection id', function (): void {
    $connection = RepositoryConnection::factory()->create();
    $job = new SyncRepositoryDocsJob($connection);

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->uniqueId())->toBe((string) $connection->id);
});

it('queues only one job when dispatched twice for the same connection', function (): void {
    Queue::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $firstConnection = RepositoryConnection::factory()->create(['project_id' => $project->id]);
    $area = Area::factory()->create(['project_id' => $project->id]);
    $secondConnection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => $area->id,
    ]);

    SyncRepositoryDocsJob::dispatch($firstConnection);
    SyncRepositoryDocsJob::dispatch($firstConnection);
    SyncRepositoryDocsJob::dispatch($secondConnection);

    Queue::assertPushed(SyncRepositoryDocsJob::class, 2);
    Queue::assertPushed(
        SyncRepositoryDocsJob::class,
        fn (SyncRepositoryDocsJob $job): bool => $job->repositoryConnection->is($firstConnection),
    );
    Queue::assertPushed(
        SyncRepositoryDocsJob::class,
        fn (SyncRepositoryDocsJob $job): bool => $job->repositoryConnection->is($secondConnection),
    );
});

it('syncs head commit into the project area notebook, prunes removed pages, stores sha and time, and clears last_error', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $area = Area::factory()->create(['project_id' => $project->id, 'name' => 'Backend']);
    $connection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => $area->id,
        'repository' => 'acme/api',
        'branch' => 'main',
        'docs_path' => 'docs',
        'last_synced_sha' => 'old-sha-000',
        'last_synced_at' => now()->subDay(),
        'last_error' => 'Previous transient failure',
    ]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'area_id' => $area->id,
        'name' => 'Docs · Backend',
        'created_by' => $owner->id,
    ]);

    NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/01-intro.md',
        'content' => ['title' => 'docs/01-intro.md', 'body' => 'Old intro'],
        'order' => 1,
        'created_by' => $owner->id,
    ]);

    NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/02-removed.md',
        'content' => ['title' => 'docs/02-removed.md', 'body' => 'To delete'],
        'order' => 2,
        'created_by' => $owner->id,
    ]);

    $manualPage = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => null,
        'content' => ['title' => 'Manual note', 'body' => 'Keep me'],
        'order' => 99,
        'created_by' => $owner->id,
    ]);

    Http::fake([
        'https://api.github.com/repos/acme/api/branches/main' => Http::response([
            'name' => 'main',
            'commit' => ['sha' => 'head-sha-222'],
        ]),
        'https://api.github.com/repos/acme/api/git/trees/head-sha-222?recursive=1' => Http::response([
            'sha' => 'head-sha-222',
            'truncated' => false,
            'tree' => [
                ['path' => 'docs/01-intro.md', 'type' => 'blob', 'sha' => 'blob-intro', 'size' => 20],
                ['path' => 'docs/03-new.md', 'type' => 'blob', 'sha' => 'blob-new', 'size' => 20],
            ],
        ]),
        'https://api.github.com/repos/acme/api/git/blobs/blob-intro' => Http::response([
            'encoding' => 'base64',
            'content' => base64_encode('# Intro Updated'),
        ]),
        'https://api.github.com/repos/acme/api/git/blobs/blob-new' => Http::response([
            'encoding' => 'base64',
            'content' => base64_encode('# New Page'),
        ]),
    ]);

    $this->travelTo(now()->startOfSecond());

    SyncRepositoryDocsJob::dispatchSync($connection);

    $connection->refresh();

    expect($connection->last_synced_sha)->toBe('head-sha-222')
        ->and($connection->last_synced_at?->equalTo(now()))->toBeTrue()
        ->and($connection->last_error)->toBeNull();

    $syncedPages = $notebook->pages()->whereNotNull('source_path')->orderBy('order')->get();

    expect($syncedPages)->toHaveCount(2)
        ->and($syncedPages[0]->source_path)->toBe('docs/01-intro.md')
        ->and($syncedPages[0]->content['body'])->toBe('# Intro Updated')
        ->and($syncedPages[1]->source_path)->toBe('docs/03-new.md')
        ->and($syncedPages[1]->content['body'])->toBe('# New Page')
        ->and($manualPage->fresh())->not->toBeNull();
});

it('skips tree fetch and notebook writes when head matches last_synced_sha', function (): void {
    $syncedAt = now()->subHours(3)->startOfSecond();
    $connection = RepositoryConnection::factory()->create([
        'repository' => 'acme/funwork',
        'branch' => 'main',
        'last_synced_sha' => 'same-sha-123',
        'last_synced_at' => $syncedAt,
    ]);

    Http::fake([
        'https://api.github.com/repos/acme/funwork/branches/main' => Http::response([
            'name' => 'main',
            'commit' => ['sha' => 'same-sha-123'],
        ]),
    ]);

    SyncRepositoryDocsJob::dispatchSync($connection);

    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/git/trees/'));

    $connection->refresh();

    expect($connection->last_synced_sha)->toBe('same-sha-123')
        ->and($connection->last_synced_at?->equalTo($syncedAt))->toBeTrue()
        ->and(Notebook::where('project_id', $connection->project_id)->exists())->toBeFalse();
});

it('keeps the existing page when a file is skipped for exceeding the size limit', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'repository' => 'acme/funwork',
        'branch' => 'main',
        'docs_path' => 'docs',
        'last_synced_sha' => 'sha-before',
    ]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'name' => 'Docs',
        'created_by' => $owner->id,
    ]);

    $hugePage = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/huge.md',
        'content' => ['title' => 'docs/huge.md', 'body' => 'Previous huge doc'],
        'order' => 1,
        'created_by' => $owner->id,
    ]);

    $deletedPage = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/removed.md',
        'content' => ['title' => 'docs/removed.md', 'body' => 'Should be pruned'],
        'order' => 2,
        'created_by' => $owner->id,
    ]);

    Http::fake([
        'https://api.github.com/repos/acme/funwork/branches/main' => Http::response([
            'name' => 'main',
            'commit' => ['sha' => 'sha-after'],
        ]),
        'https://api.github.com/repos/acme/funwork/git/trees/sha-after?recursive=1' => Http::response([
            'sha' => 'sha-after',
            'truncated' => false,
            'tree' => [
                ['path' => 'docs/huge.md', 'type' => 'blob', 'sha' => 'blob-huge', 'size' => GitHubTokenDocsSource::MAX_FILE_BYTES + 1],
                ['path' => 'docs/small.md', 'type' => 'blob', 'sha' => 'blob-small', 'size' => 10],
            ],
        ]),
        'https://api.github.com/repos/acme/funwork/git/blobs/blob-small' => Http::response([
            'encoding' => 'base64',
            'content' => base64_encode('# Small'),
        ]),
    ]);

    SyncRepositoryDocsJob::dispatchSync($connection);

    expect($hugePage->fresh())->not->toBeNull()
        ->and($hugePage->fresh()->content)->toBe(['title' => 'docs/huge.md', 'body' => 'Previous huge doc'])
        ->and($deletedPage->fresh())->toBeNull()
        ->and($notebook->pages()->where('source_path', 'docs/small.md')->exists())->toBeTrue()
        ->and($connection->fresh()->last_synced_sha)->toBe('sha-after');
});

it('propagates exceptions without altering notebook pages, last_synced_sha, or last_error', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $syncedAt = now()->subDay()->startOfSecond();
    $connection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'repository' => 'acme/funwork',
        'branch' => 'main',
        'docs_path' => 'docs',
        'last_synced_sha' => 'sha-good',
        'last_synced_at' => $syncedAt,
        'last_error' => 'Old error',
    ]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'name' => 'Docs',
        'created_by' => $owner->id,
    ]);

    $existingPage = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/intro.md',
        'content' => ['title' => 'docs/intro.md', 'body' => 'Original'],
        'order' => 1,
        'created_by' => $owner->id,
    ]);

    Http::fake([
        'https://api.github.com/repos/acme/funwork/branches/main' => Http::response([
            'message' => 'Bad credentials',
        ], 401),
    ]);

    expect(fn () => SyncRepositoryDocsJob::dispatchSync($connection))
        ->toThrow(RepositoryAccessDeniedException::class);

    $connection->refresh();

    expect($connection->last_synced_sha)->toBe('sha-good')
        ->and($connection->last_synced_at?->equalTo($syncedAt))->toBeTrue()
        ->and($connection->last_error)->toBe('Old error')
        ->and($existingPage->fresh()->content)->toBe(['title' => 'docs/intro.md', 'body' => 'Original'])
        ->and($notebook->pages()->count())->toBe(1);
});

it('creates the default Docs notebook on first sync for a connection without an area', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => null,
        'repository' => 'acme/funwork',
        'branch' => 'main',
        'docs_path' => 'docs',
    ]);

    Http::fake([
        'https://api.github.com/repos/acme/funwork/branches/main' => Http::response([
            'name' => 'main',
            'commit' => ['sha' => 'initial-sha-001'],
        ]),
        'https://api.github.com/repos/acme/funwork/git/trees/initial-sha-001?recursive=1' => Http::response([
            'sha' => 'initial-sha-001',
            'truncated' => false,
            'tree' => [
                ['path' => 'docs/README.md', 'type' => 'blob', 'sha' => 'blob-readme', 'size' => 12],
            ],
        ]),
        'https://api.github.com/repos/acme/funwork/git/blobs/blob-readme' => Http::response([
            'encoding' => 'base64',
            'content' => base64_encode('# Welcome'),
        ]),
    ]);

    SyncRepositoryDocsJob::dispatchSync($connection);

    $notebook = Notebook::where('project_id', $project->id)->where('name', 'Docs')->sole();

    expect($notebook->area_id)->toBeNull()
        ->and($notebook->pages()->sole()->source_path)->toBe('docs/README.md')
        ->and($notebook->pages()->sole()->content['body'])->toBe('# Welcome')
        ->and($connection->fresh()->last_synced_sha)->toBe('initial-sha-001');
});
