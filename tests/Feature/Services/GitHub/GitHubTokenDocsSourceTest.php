<?php

use App\Contracts\RepositoryDocsSource;
use App\Data\RepositoryConnections\RepositoryDocsSnapshot;
use App\Exceptions\RepositoryConnections\RepositoryAccessDeniedException;
use App\Exceptions\RepositoryConnections\RepositoryRateLimitedException;
use App\Exceptions\RepositoryConnections\RepositoryTreeTruncatedException;
use App\Models\RepositoryConnection;
use App\Services\GitHub\GitHubTokenDocsSource;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

function githubConnection(array $attributes = []): RepositoryConnection
{
    return RepositoryConnection::factory()->make([
        'repository' => 'acme/funwork',
        'branch' => 'main',
        'docs_path' => 'docs',
        'token' => 'ghp_super_secret_token_9999',
        ...$attributes,
    ]);
}

it('binds RepositoryDocsSource to GitHubTokenDocsSource', function (): void {
    expect(app(RepositoryDocsSource::class))->toBeInstanceOf(GitHubTokenDocsSource::class);
});

it('fetches the head commit sha from the GitHub branch endpoint with expected headers', function (): void {
    Http::fake([
        'https://api.github.com/repos/acme/funwork/branches/main' => Http::response([
            'name' => 'main',
            'commit' => ['sha' => 'abc123def456'],
        ]),
    ]);

    $connection = githubConnection();
    $sha = app(RepositoryDocsSource::class)->headCommit($connection);

    expect($sha)->toBe('abc123def456');

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'GET'
            && $request->url() === 'https://api.github.com/repos/acme/funwork/branches/main'
            && $request->hasHeader('Authorization', 'Bearer ghp_super_secret_token_9999')
            && $request->hasHeader('Accept', 'application/vnd.github+json')
            && $request->hasHeader('X-GitHub-Api-Version');
    });
});

it('fetches the full recursive tree, filters eligible markdown blobs, and decodes base64 UTF-8 bodies', function (): void {
    $utf8Body = "# Documentación\n\nTexto con ñ y · separador.\n";
    $chunkedBase64 = chunk_split(base64_encode($utf8Body), 12, "\n");

    Http::fake([
        'https://api.github.com/repos/acme/funwork/git/trees/commit-sha-1?recursive=1' => Http::response([
            'sha' => 'commit-sha-1',
            'truncated' => false,
            'tree' => [
                ['path' => 'docs', 'type' => 'tree', 'sha' => 'tree-docs'],
                ['path' => 'README.md', 'type' => 'blob', 'sha' => 'blob-root', 'size' => 20],
                ['path' => 'src/App.php', 'type' => 'blob', 'sha' => 'blob-src', 'size' => 50],
                ['path' => 'docs-extra/other.md', 'type' => 'blob', 'sha' => 'blob-prefix-trap', 'size' => 20],
                ['path' => 'docs/image.png', 'type' => 'blob', 'sha' => 'blob-png', 'size' => 200],
                ['path' => 'docs/notes.txt', 'type' => 'blob', 'sha' => 'blob-txt', 'size' => 30],
                ['path' => 'docs/archive/old.md', 'type' => 'blob', 'sha' => 'blob-archive-1', 'size' => 40],
                ['path' => 'docs/archive/nested/deep.md', 'type' => 'blob', 'sha' => 'blob-archive-2', 'size' => 40],
                ['path' => 'docs/README.md', 'type' => 'blob', 'sha' => 'blob-readme', 'size' => strlen($utf8Body)],
                ['path' => 'docs/areas/backend.md', 'type' => 'blob', 'sha' => 'blob-backend', 'size' => 15],
                ['path' => 'docs/archive-notes.md', 'type' => 'blob', 'sha' => 'blob-archive-notes', 'size' => 16],
            ],
        ]),
        'https://api.github.com/repos/acme/funwork/git/blobs/blob-readme' => Http::response([
            'sha' => 'blob-readme',
            'encoding' => 'base64',
            'content' => $chunkedBase64,
        ]),
        'https://api.github.com/repos/acme/funwork/git/blobs/blob-backend' => Http::response([
            'sha' => 'blob-backend',
            'encoding' => 'base64',
            'content' => base64_encode('# Backend Area'),
        ]),
        'https://api.github.com/repos/acme/funwork/git/blobs/blob-archive-notes' => Http::response([
            'sha' => 'blob-archive-notes',
            'encoding' => 'base64',
            'content' => base64_encode('# Archive Notes'),
        ]),
    ]);

    $snapshot = app(RepositoryDocsSource::class)->fetchDocs(githubConnection(), 'commit-sha-1');

    expect($snapshot)->toBeInstanceOf(RepositoryDocsSnapshot::class)
        ->and($snapshot->commitSha)->toBe('commit-sha-1')
        ->and($snapshot->skipped)->toBe([])
        ->and($snapshot->files)->toBe([
            'docs/archive-notes.md' => '# Archive Notes',
            'docs/areas/backend.md' => '# Backend Area',
            'docs/README.md' => $utf8Body,
        ]);

    Http::assertSentCount(4);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer ghp_super_secret_token_9999'));
});

it('uses custom docs_path as the key prefix and excludes custom docs_path archive', function (): void {
    Http::fake([
        'https://api.github.com/repos/acme/funwork/git/trees/commit-sha-2?recursive=1' => Http::response([
            'sha' => 'commit-sha-2',
            'truncated' => false,
            'tree' => [
                ['path' => 'docs/ignored.md', 'type' => 'blob', 'sha' => 'blob-default-docs', 'size' => 10],
                ['path' => 'apps/api/docs/archive/old.md', 'type' => 'blob', 'sha' => 'blob-archived', 'size' => 10],
                ['path' => 'apps/api/docs/guides/auth.md', 'type' => 'blob', 'sha' => 'blob-auth', 'size' => 12],
            ],
        ]),
        'https://api.github.com/repos/acme/funwork/git/blobs/blob-auth' => Http::response([
            'sha' => 'blob-auth',
            'encoding' => 'base64',
            'content' => base64_encode('# Auth Guide'),
        ]),
    ]);

    $connection = githubConnection(['docs_path' => 'apps/api/docs']);
    $snapshot = app(RepositoryDocsSource::class)->fetchDocs($connection, 'commit-sha-2');

    expect($snapshot->files)->toBe([
        'apps/api/docs/guides/auth.md' => '# Auth Guide',
    ]);

    Http::assertSentCount(2);
});

it('throws RepositoryTreeTruncatedException and makes no blob requests when tree is truncated', function (): void {
    Http::fake([
        'https://api.github.com/repos/acme/funwork/git/trees/commit-sha-3?recursive=1' => Http::response([
            'sha' => 'commit-sha-3',
            'truncated' => true,
            'tree' => [
                ['path' => 'docs/README.md', 'type' => 'blob', 'sha' => 'blob-readme', 'size' => 10],
            ],
        ]),
    ]);

    $connection = githubConnection();

    expect(fn (): RepositoryDocsSnapshot => app(RepositoryDocsSource::class)->fetchDocs($connection, 'commit-sha-3'))
        ->toThrow(RepositoryTreeTruncatedException::class);

    Http::assertSentCount(1);
});

it('skips files exceeding max bytes without downloading their blobs', function (): void {
    Http::fake([
        'https://api.github.com/repos/acme/funwork/git/trees/commit-sha-4?recursive=1' => Http::response([
            'sha' => 'commit-sha-4',
            'truncated' => false,
            'tree' => [
                ['path' => 'docs/huge.md', 'type' => 'blob', 'sha' => 'blob-huge', 'size' => GitHubTokenDocsSource::MAX_FILE_BYTES + 1],
                ['path' => 'docs/small.md', 'type' => 'blob', 'sha' => 'blob-small', 'size' => 10],
            ],
        ]),
        'https://api.github.com/repos/acme/funwork/git/blobs/blob-small' => Http::response([
            'sha' => 'blob-small',
            'encoding' => 'base64',
            'content' => base64_encode('# Small'),
        ]),
    ]);

    $snapshot = app(RepositoryDocsSource::class)->fetchDocs(githubConnection(), 'commit-sha-4');

    expect($snapshot->files)->toBe(['docs/small.md' => '# Small'])
        ->and($snapshot->skipped)->toHaveCount(1)
        ->and($snapshot->skipped[0]['path'])->toBe('docs/huge.md')
        ->and($snapshot->skipped[0]['reason'])->not->toBeEmpty();

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'blob-huge'));
});

it('enforces the maximum files per snapshot limit and skips excess files without downloading them', function (): void {
    $tree = [];
    $fakes = [
        'https://api.github.com/repos/acme/funwork/git/trees/commit-sha-5?recursive=1' => function () use (&$tree) {
            return Http::response([
                'sha' => 'commit-sha-5',
                'truncated' => false,
                'tree' => $tree,
            ]);
        },
        'https://api.github.com/repos/acme/funwork/git/blobs/*' => Http::response([
            'encoding' => 'base64',
            'content' => base64_encode('# Page'),
        ]),
    ];

    $totalFiles = GitHubTokenDocsSource::MAX_FILES + 2;

    for ($index = 1; $index <= $totalFiles; $index++) {
        $padded = str_pad((string) $index, 4, '0', STR_PAD_LEFT);
        $tree[] = [
            'path' => "docs/page-{$padded}.md",
            'type' => 'blob',
            'sha' => "blob-{$padded}",
            'size' => 10,
        ];
    }

    Http::fake($fakes);

    $snapshot = app(RepositoryDocsSource::class)->fetchDocs(githubConnection(), 'commit-sha-5');

    expect($snapshot->files)->toHaveCount(GitHubTokenDocsSource::MAX_FILES)
        ->and($snapshot->skipped)->toHaveCount(2)
        ->and(array_column($snapshot->skipped, 'path'))->each->toStartWith('docs/page-');

    Http::assertSentCount(1 + GitHubTokenDocsSource::MAX_FILES);
});

it('throws RepositoryAccessDeniedException for 401, 403, and 404 without leaking the token', function (int $status, array $headers = []): void {
    $token = 'ghp_secret_leak_check_4242';

    Http::fake([
        'https://api.github.com/repos/acme/funwork/*' => Http::response(['message' => 'Denied'], $status, $headers),
    ]);

    $connection = githubConnection(['token' => $token]);

    try {
        app(RepositoryDocsSource::class)->headCommit($connection);
        $this->fail('Expected RepositoryAccessDeniedException to be thrown.');
    } catch (RepositoryAccessDeniedException $exception) {
        expect($exception->getMessage())->not->toContain($token);
    }

    try {
        app(RepositoryDocsSource::class)->fetchDocs($connection, 'commit-sha');
        $this->fail('Expected RepositoryAccessDeniedException to be thrown.');
    } catch (RepositoryAccessDeniedException $exception) {
        expect($exception->getMessage())->not->toContain($token);
    }
})->with([
    'unauthorized' => [401],
    'forbidden' => [403],
    'forbidden with remaining rate limit' => [403, ['X-RateLimit-Remaining' => '42']],
    'not found' => [404],
]);

it('throws RepositoryRateLimitedException for 403 or 429 when x-ratelimit-remaining is 0 without leaking the token', function (int $status): void {
    $token = 'ghp_secret_rate_limit_8888';

    Http::fake([
        'https://api.github.com/repos/acme/funwork/*' => Http::response(
            ['message' => 'API rate limit exceeded'],
            $status,
            ['X-RateLimit-Remaining' => '0'],
        ),
    ]);

    $connection = githubConnection(['token' => $token]);

    try {
        app(RepositoryDocsSource::class)->headCommit($connection);
        $this->fail('Expected RepositoryRateLimitedException to be thrown.');
    } catch (RepositoryRateLimitedException $exception) {
        expect($exception->getMessage())->not->toContain($token);
    }
})->with([
    'forbidden rate limited' => 403,
    'too many requests rate limited' => 429,
]);

it('throws RequestException for unexpected HTTP server errors without leaking the token', function (): void {
    $token = 'ghp_secret_server_error_7777';

    Http::fake([
        'https://api.github.com/repos/acme/funwork/*' => Http::response(['message' => 'Server Error'], 500),
    ]);

    $connection = githubConnection(['token' => $token]);

    try {
        app(RepositoryDocsSource::class)->headCommit($connection);
        $this->fail('Expected RequestException to be thrown.');
    } catch (RequestException $exception) {
        expect($exception->getMessage())->not->toContain($token);
    }
});
