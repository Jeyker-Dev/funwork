<?php

use App\Jobs\SyncRepositoryDocsJob;
use App\Models\RepositoryConnection;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

function signWebhookPayload(string $rawBody, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $rawBody, $secret);
}

/**
 * @param  array<string, mixed>  $payload
 */
function postGitHubWebhook(
    RepositoryConnection|int $connection,
    array $payload = [],
    string $event = 'push',
    ?string $signature = null,
    bool $omitSignature = false,
): TestResponse {
    $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
    $headers = [
        'X-GitHub-Event' => $event,
    ];

    if (! $omitSignature) {
        if ($signature !== null) {
            $headers['X-Hub-Signature-256'] = $signature;
        } elseif ($connection instanceof RepositoryConnection) {
            $headers['X-Hub-Signature-256'] = signWebhookPayload($rawBody, (string) $connection->webhook_secret);
        }
    }

    return test()->call(
        'POST',
        route('webhooks.github.repository-connections', ['connection' => $connection]),
        server: collect($headers)
            ->mapWithKeys(fn (string $value, string $name): array => ['HTTP_'.strtoupper(str_replace('-', '_', $name)) => $value])
            ->merge(['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'])
            ->all(),
        content: $rawBody,
    );
}

it('dispatches SyncRepositoryDocsJob on a valid signed push touching docs_path', function (string $changeType, string $path, string $docsPath): void {
    Queue::fake();

    $connection = RepositoryConnection::factory()->create([
        'branch' => 'main',
        'docs_path' => $docsPath,
    ]);

    $response = postGitHubWebhook($connection, [
        'ref' => 'refs/heads/main',
        'deleted' => false,
        'commits' => [
            [
                'id' => 'commit-1',
                $changeType => [$path],
            ],
        ],
    ]);

    $response->assertAccepted();

    Queue::assertPushed(
        SyncRepositoryDocsJob::class,
        fn (SyncRepositoryDocsJob $job): bool => $job->repositoryConnection->is($connection),
    );
})->with([
    'added under default docs' => ['added', 'docs/guide.md', 'docs'],
    'modified under default docs' => ['modified', 'docs/areas/backend.md', 'docs'],
    'removed under default docs' => ['removed', 'docs/old.md', 'docs'],
    'added under nested docs path' => ['added', 'apps/api/docs/intro.md', 'apps/api/docs'],
]);

it('rejects missing, wrong, or malformed signatures with 401 and dispatches nothing', function (?string $signature, bool $omitSignature, string $event): void {
    Queue::fake();

    $connection = RepositoryConnection::factory()->create([
        'branch' => 'main',
        'docs_path' => 'docs',
        'webhook_secret' => 'super-secret-webhook-key',
    ]);

    $response = postGitHubWebhook(
        $connection,
        [
            'ref' => 'refs/heads/main',
            'commits' => [['added' => ['docs/README.md']]],
        ],
        event: $event,
        signature: $signature,
        omitSignature: $omitSignature,
    );

    $response->assertUnauthorized();
    Queue::assertNothingPushed();
})->with([
    'missing signature on push' => [null, true, 'push'],
    'missing signature on ping' => [null, true, 'ping'],
    'wrong hmac' => ['sha256='.str_repeat('a', 64), false, 'push'],
    'malformed without prefix' => ['not-a-sha256-header', false, 'push'],
    'empty signature header' => ['', false, 'push'],
]);

it('returns 200 for a signed ping event and dispatches nothing', function (): void {
    Queue::fake();

    $connection = RepositoryConnection::factory()->create();

    $response = postGitHubWebhook($connection, ['zen' => 'Keep it logically awesome.'], event: 'ping');

    $response->assertOk();
    Queue::assertNothingPushed();
});

it('returns 202 and ignores non-push events', function (string $event): void {
    Queue::fake();

    $connection = RepositoryConnection::factory()->create();

    $response = postGitHubWebhook($connection, [
        'action' => 'opened',
        'ref' => 'refs/heads/main',
    ], event: $event);

    $response->assertAccepted();
    Queue::assertNothingPushed();
})->with(['pull_request', 'issues', 'repository', '']);

it('returns 202 and ignores pushes to other branches, tags, deleted branches, or non-docs files', function (array $payload): void {
    Queue::fake();

    $connection = RepositoryConnection::factory()->create([
        'branch' => 'main',
        'docs_path' => 'docs',
    ]);

    $response = postGitHubWebhook($connection, $payload);

    $response->assertAccepted();
    Queue::assertNothingPushed();
})->with([
    'other branch' => [[
        'ref' => 'refs/heads/develop',
        'deleted' => false,
        'commits' => [['added' => ['docs/README.md']]],
    ]],
    'tag push' => [[
        'ref' => 'refs/tags/v1.0.0',
        'deleted' => false,
        'commits' => [['added' => ['docs/README.md']]],
    ]],
    'deleted target branch' => [[
        'ref' => 'refs/heads/main',
        'deleted' => true,
        'commits' => [],
    ]],
    'only non-docs changes' => [[
        'ref' => 'refs/heads/main',
        'deleted' => false,
        'commits' => [[
            'added' => ['src/Service.php'],
            'modified' => ['README.md', 'docs-extra/guide.md'],
            'removed' => ['tests/OldTest.php'],
        ]],
    ]],
]);

it('dispatches when a push to the target branch has empty commits, missing file lists, or hits the 20-commit cap', function (array $payload): void {
    Queue::fake();

    $connection = RepositoryConnection::factory()->create([
        'branch' => 'main',
        'docs_path' => 'docs',
    ]);

    $response = postGitHubWebhook($connection, $payload);

    $response->assertAccepted();

    Queue::assertPushed(
        SyncRepositoryDocsJob::class,
        fn (SyncRepositoryDocsJob $job): bool => $job->repositoryConnection->is($connection),
    );
})->with([
    'empty commits list' => [[
        'ref' => 'refs/heads/main',
        'deleted' => false,
        'commits' => [],
    ]],
    'missing commits key' => [[
        'ref' => 'refs/heads/main',
        'deleted' => false,
    ]],
    'commit without file lists' => [[
        'ref' => 'refs/heads/main',
        'deleted' => false,
        'commits' => [['id' => 'abc123', 'message' => 'Merge']],
    ]],
    '20-commit cap reached' => [[
        'ref' => 'refs/heads/main',
        'deleted' => false,
        'commits' => array_fill(0, 20, ['modified' => ['src/App.php']]),
    ]],
]);

it('works without a CSRF token even when CSRF middleware is active', function (): void {
    Queue::fake();

    $connection = RepositoryConnection::factory()->create([
        'branch' => 'main',
        'docs_path' => 'docs',
    ]);

    app()['env'] = 'production';

    try {
        $response = postGitHubWebhook($connection, [
            'ref' => 'refs/heads/main',
            'deleted' => false,
            'commits' => [['added' => ['docs/README.md']]],
        ]);
    } finally {
        app()['env'] = 'testing';
    }

    $response->assertAccepted();
    Queue::assertPushed(SyncRepositoryDocsJob::class, 1);
});

it('returns 404 for an unknown connection id', function (): void {
    Queue::fake();

    $response = postGitHubWebhook(999999, ['zen' => 'Missing'], event: 'ping', signature: 'sha256=irrelevant');

    $response->assertNotFound();
    Queue::assertNothingPushed();
});

it('registers the named webhook route with a named throttle middleware', function (): void {
    $route = Route::getRoutes()->getByName('webhooks.github.repository-connections');

    expect($route)->toBeInstanceOf(RoutingRoute::class)
        ->and($route->uri())->toBe('webhooks/github/repository-connections/{connection}')
        ->and($route->methods())->toContain('POST')
        ->and($route->gatherMiddleware())->toContain('throttle:github-webhooks');
});

it('rate limits excessive webhook requests per connection', function (): void {
    Queue::fake();

    $connection = RepositoryConnection::factory()->create();

    for ($attempt = 0; $attempt < 60; $attempt++) {
        postGitHubWebhook($connection, ['zen' => 'Keep it logically awesome.'], event: 'ping')
            ->assertOk();
    }

    postGitHubWebhook($connection, ['zen' => 'Too many'], event: 'ping')
        ->assertTooManyRequests();

    Queue::assertNothingPushed();
});
