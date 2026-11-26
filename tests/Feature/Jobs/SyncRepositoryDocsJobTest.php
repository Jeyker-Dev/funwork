<?php

use App\Enums\RepositoryConnectionStatus;
use App\Exceptions\RepositoryConnections\RepositoryAccessDeniedException;
use App\Exceptions\RepositoryConnections\RepositoryTreeTruncatedException;
use App\Jobs\SyncRepositoryDocsJob;
use App\Models\Area;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Notifications\RepositoryConnectionBrokenNotification;
use App\Services\GitHub\GitHubTokenDocsSource;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Pipeline;
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

it('handles access denied responses (401, 403, 404) by marking broken, storing a safe last_error, keeping pages, failing without retry, and notifying once', function (int $status): void {
    Notification::fake();

    $token = 'github_pat_sensitive_token_9876';
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $syncedAt = now()->subDay()->startOfSecond();
    $connection = RepositoryConnection::factory()->active()->create([
        'project_id' => $project->id,
        'repository' => 'acme/funwork',
        'branch' => 'main',
        'docs_path' => 'docs',
        'token' => $token,
        'last_synced_sha' => 'sha-good',
        'last_synced_at' => $syncedAt,
        'last_error' => null,
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
            'message' => "Bad credentials using {$token}",
        ], $status, ['X-Secret-Header' => $token]),
    ]);

    $job = (new SyncRepositoryDocsJob($connection))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    $job->assertFailedWith(RepositoryAccessDeniedException::class);

    $connection->refresh();

    expect($connection->status)->toBe(RepositoryConnectionStatus::Broken)
        ->and($connection->last_error)->not->toBeNull()
        ->and($connection->last_error)->toContain((string) $status)
        ->and($connection->last_error)->not->toContain($token)
        ->and($connection->last_error)->not->toContain('Bad credentials')
        ->and($connection->last_synced_sha)->toBe('sha-good')
        ->and($connection->last_synced_at?->equalTo($syncedAt))->toBeTrue()
        ->and($existingPage->fresh()->content)->toBe(['title' => 'docs/intro.md', 'body' => 'Original'])
        ->and($notebook->pages()->count())->toBe(1);

    Notification::assertSentToTimes($owner, RepositoryConnectionBrokenNotification::class, 1);
    Notification::assertSentTo(
        $owner,
        RepositoryConnectionBrokenNotification::class,
        function (RepositoryConnectionBrokenNotification $notification) use ($connection, $project, $token): bool {
            $payload = $notification->toDatabase($connection->project->owner);

            return $payload['repository'] === 'acme/funwork'
                && $payload['url'] === route('project.settings', ['project' => $project->id])
                && ! str_contains(json_encode($payload, JSON_THROW_ON_ERROR), $token);
        },
    );
})->with([
    'unauthorized 401' => 401,
    'forbidden 403' => 403,
    'not found 404' => 404,
]);

it('does not send a duplicate notification when an already broken connection fails with access denied again', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->broken('Previous 401 error')->create([
        'project_id' => $project->id,
        'repository' => 'acme/funwork',
        'branch' => 'main',
    ]);

    Http::fake([
        'https://api.github.com/repos/acme/funwork/branches/main' => Http::response([
            'message' => 'Not Found',
        ], 404),
    ]);

    $job = (new SyncRepositoryDocsJob($connection))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    $job->assertFailedWith(RepositoryAccessDeniedException::class);

    $connection->refresh();

    expect($connection->status)->toBe(RepositoryConnectionStatus::Broken)
        ->and($connection->last_error)->toContain('404');

    Notification::assertNothingSent();
});

it('releases rate-limited jobs without changing status or notifying', function (int $status): void {
    Notification::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->active()->create([
        'project_id' => $project->id,
        'repository' => 'acme/funwork',
        'branch' => 'main',
        'last_synced_sha' => 'sha-good',
        'last_error' => null,
    ]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'name' => 'Docs',
        'created_by' => $owner->id,
    ]);

    $page = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/intro.md',
        'content' => ['title' => 'docs/intro.md', 'body' => 'Original'],
        'created_by' => $owner->id,
    ]);

    Http::fake([
        'https://api.github.com/repos/acme/funwork/branches/main' => Http::response(
            ['message' => 'API rate limit exceeded'],
            $status,
            ['X-RateLimit-Remaining' => '0'],
        ),
    ]);

    $job = (new SyncRepositoryDocsJob($connection))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    $job->assertReleased(delay: SyncRepositoryDocsJob::RATE_LIMIT_RELEASE_SECONDS);
    $job->assertNotFailed();

    $connection->refresh();

    expect($connection->status)->toBe(RepositoryConnectionStatus::Active)
        ->and($connection->last_error)->toBeNull()
        ->and($connection->last_synced_sha)->toBe('sha-good')
        ->and($page->fresh())->not->toBeNull();

    Notification::assertNothingSent();
})->with([
    'forbidden rate limit 403' => 403,
    'too many requests 429' => 429,
]);

it('stores last_error, keeps status active, keeps pages, fails without retry, and does not notify on truncated tree', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->active()->create([
        'project_id' => $project->id,
        'repository' => 'acme/funwork',
        'branch' => 'main',
        'last_synced_sha' => 'sha-good',
    ]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'name' => 'Docs',
        'created_by' => $owner->id,
    ]);

    $page = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/intro.md',
        'content' => ['title' => 'docs/intro.md', 'body' => 'Original'],
        'created_by' => $owner->id,
    ]);

    Http::fake([
        'https://api.github.com/repos/acme/funwork/branches/main' => Http::response([
            'name' => 'main',
            'commit' => ['sha' => 'sha-new'],
        ]),
        'https://api.github.com/repos/acme/funwork/git/trees/sha-new?recursive=1' => Http::response([
            'sha' => 'sha-new',
            'truncated' => true,
            'tree' => [],
        ]),
    ]);

    $job = (new SyncRepositoryDocsJob($connection))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    $job->assertFailedWith(RepositoryTreeTruncatedException::class);

    $connection->refresh();

    expect($connection->status)->toBe(RepositoryConnectionStatus::Active)
        ->and($connection->last_error)->not->toBeNull()
        ->and($connection->last_error)->toContain('truncated')
        ->and($connection->last_synced_sha)->toBe('sha-good')
        ->and($page->fresh())->not->toBeNull();

    Notification::assertNothingSent();
});

it('propagates unexpected errors for retries and stores a safe last_error in failed() without leaking the token or response body', function (): void {
    Notification::fake();

    $token = 'github_pat_unexpected_secret_5555';
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->active()->create([
        'project_id' => $project->id,
        'repository' => 'acme/funwork',
        'branch' => 'main',
        'token' => $token,
        'last_synced_sha' => 'sha-good',
        'last_error' => null,
    ]);

    Http::fake([
        'https://api.github.com/repos/acme/funwork/branches/main' => Http::response(
            "Internal error echoing {$token}",
            502,
        ),
    ]);

    $job = new SyncRepositoryDocsJob($connection);
    $caught = null;

    try {
        app()->call([$job, 'handle']);
    } catch (RequestException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf(RequestException::class);

    $job->failed($caught);
    $connection->refresh();

    expect($connection->status)->toBe(RepositoryConnectionStatus::Active)
        ->and($connection->last_error)->not->toBeNull()
        ->and($connection->last_error)->toContain('502')
        ->and($connection->last_error)->not->toContain($token)
        ->and($connection->last_error)->not->toContain('Internal error echoing');

    Notification::assertNothingSent();
});

it('uses WithoutOverlapping middleware keyed by connection id and releases overlapping runs', function (): void {
    $first = RepositoryConnection::factory()->create();
    $second = RepositoryConnection::factory()->create();

    $job = new SyncRepositoryDocsJob($first);
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->key)->toBe((string) $first->id)
        ->and($middleware[0]->releaseAfter)->toBe(SyncRepositoryDocsJob::OVERLAP_RELEASE_SECONDS)
        ->and($middleware[0]->expiresAfter)->toBe(SyncRepositoryDocsJob::OVERLAP_EXPIRE_SECONDS);

    $lockKey = 'laravel-queue-overlap:'.SyncRepositoryDocsJob::class.':'.$first->id;
    $lock = Cache::lock($lockKey, 60);
    expect($lock->get())->toBeTrue();

    try {
        $overlappingJob = (new SyncRepositoryDocsJob($first))->withFakeQueueInteractions();
        $handled = false;

        Pipeline::send($overlappingJob)
            ->through($overlappingJob->middleware())
            ->then(function () use (&$handled): void {
                $handled = true;
            });

        expect($handled)->toBeFalse();
        $overlappingJob->assertReleased(delay: SyncRepositoryDocsJob::OVERLAP_RELEASE_SECONDS);

        $otherJob = (new SyncRepositoryDocsJob($second))->withFakeQueueInteractions();
        $otherHandled = false;

        Pipeline::send($otherJob)
            ->through($otherJob->middleware())
            ->then(function () use (&$otherHandled): void {
                $otherHandled = true;
            });

        expect($otherHandled)->toBeTrue();
    } finally {
        $lock->release();
    }
});

it('restores a broken connection back to active and clears last_error on successful sync even at the same commit sha', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->broken('GitHub authentication failed (HTTP 401).')->create([
        'project_id' => $project->id,
        'repository' => 'acme/funwork',
        'branch' => 'main',
        'docs_path' => 'docs',
        'last_synced_sha' => 'sha-same',
    ]);

    Http::fake([
        'https://api.github.com/repos/acme/funwork/branches/main' => Http::response([
            'name' => 'main',
            'commit' => ['sha' => 'sha-same'],
        ]),
        'https://api.github.com/repos/acme/funwork/git/trees/sha-same?recursive=1' => Http::response([
            'sha' => 'sha-same',
            'truncated' => false,
            'tree' => [
                ['path' => 'docs/README.md', 'type' => 'blob', 'sha' => 'blob-readme', 'size' => 10],
            ],
        ]),
        'https://api.github.com/repos/acme/funwork/git/blobs/blob-readme' => Http::response([
            'encoding' => 'base64',
            'content' => base64_encode('# Restored'),
        ]),
    ]);

    SyncRepositoryDocsJob::dispatchSync($connection);

    $connection->refresh();

    expect($connection->status)->toBe(RepositoryConnectionStatus::Active)
        ->and($connection->last_error)->toBeNull()
        ->and($connection->last_synced_sha)->toBe('sha-same')
        ->and(Notebook::where('project_id', $project->id)->sole()->pages()->sole()->content['body'])->toBe('# Restored');
});

it('preserves pages and last_synced_sha if access denied happens during tree fetch after headCommit succeeds', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->active()->create([
        'project_id' => $project->id,
        'repository' => 'acme/funwork',
        'branch' => 'main',
        'docs_path' => 'docs',
        'last_synced_sha' => 'sha-old',
    ]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'name' => 'Docs',
        'created_by' => $owner->id,
    ]);

    $page = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/keep.md',
        'content' => ['title' => 'docs/keep.md', 'body' => 'Keep me'],
        'created_by' => $owner->id,
    ]);

    Http::fake([
        'https://api.github.com/repos/acme/funwork/branches/main' => Http::response([
            'name' => 'main',
            'commit' => ['sha' => 'sha-new'],
        ]),
        'https://api.github.com/repos/acme/funwork/git/trees/sha-new?recursive=1' => Http::response([
            'message' => 'Resource not accessible by personal access token',
        ], 403),
    ]);

    $job = (new SyncRepositoryDocsJob($connection))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    $job->assertFailedWith(RepositoryAccessDeniedException::class);

    $connection->refresh();

    expect($connection->status)->toBe(RepositoryConnectionStatus::Broken)
        ->and($connection->last_error)->toContain('403')
        ->and($connection->last_synced_sha)->toBe('sha-old')
        ->and($page->fresh()->content)->toBe(['title' => 'docs/keep.md', 'body' => 'Keep me'])
        ->and($notebook->pages()->count())->toBe(1);

    Notification::assertSentToTimes($owner, RepositoryConnectionBrokenNotification::class, 1);
});

it('stores a safe last_error when ConnectionException exhausts retries in failed()', function (): void {
    Notification::fake();

    $token = 'github_pat_conn_secret_1111';
    $connection = RepositoryConnection::factory()->active()->create([
        'token' => $token,
        'last_error' => null,
    ]);

    $job = new SyncRepositoryDocsJob($connection);
    $job->failed(new ConnectionException("cURL error 28 with {$token}"));

    $connection->refresh();

    expect($connection->status)->toBe(RepositoryConnectionStatus::Active)
        ->and($connection->last_error)->toBe('Could not connect to GitHub.')
        ->and($connection->last_error)->not->toContain($token);

    Notification::assertNothingSent();
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
