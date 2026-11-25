<?php

namespace App\Services\GitHub;

use App\Contracts\RepositoryDocsSource;
use App\Data\RepositoryConnections\RepositoryDocsSnapshot;
use App\Exceptions\RepositoryConnections\RepositoryAccessDeniedException;
use App\Exceptions\RepositoryConnections\RepositoryRateLimitedException;
use App\Exceptions\RepositoryConnections\RepositoryTreeTruncatedException;
use App\Models\RepositoryConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches repository markdown documentation from the GitHub REST API using a personal access token.
 */
class GitHubTokenDocsSource implements RepositoryDocsSource
{
    public const int MAX_FILES = 200;

    public const int MAX_FILE_BYTES = 524_288;

    private const string DEFAULT_BASE_URL = 'https://api.github.com';

    private const string API_VERSION = '2022-11-28';

    private const int DEFAULT_TIMEOUT_SECONDS = 15;

    private const string ACCEPT_HEADER = 'application/vnd.github+json';

    private const string ARCHIVE_PREFIX = 'archive/';

    private const string MARKDOWN_EXTENSION = '.md';

    private const string BLOB_TYPE = 'blob';

    public function headCommit(RepositoryConnection $connection): string
    {
        $response = $this->get(
            $connection,
            "/repos/{$this->encodedRepository($connection)}/branches/{$this->encodedBranch($connection)}",
        );

        $sha = $response->json('commit.sha');

        if (! is_string($sha) || $sha === '') {
            throw new RuntimeException("GitHub did not return a commit SHA for [{$connection->repository}] on branch [{$connection->branch}].");
        }

        return $sha;
    }

    public function fetchDocs(RepositoryConnection $connection, string $commitSha): RepositoryDocsSnapshot
    {
        $treeResponse = $this->get(
            $connection,
            "/repos/{$this->encodedRepository($connection)}/git/trees/".rawurlencode($commitSha),
            ['recursive' => 1],
        );

        if ((bool) $treeResponse->json('truncated', false) === true) {
            throw RepositoryTreeTruncatedException::forCommit($connection, $commitSha);
        }

        $rawEntries = $treeResponse->json('tree', []);
        $entries = $this->filterEligibleEntries(
            is_array($rawEntries) ? $rawEntries : [],
            $connection->docs_path,
        );

        $files = [];
        $skipped = [];

        foreach ($entries as $entry) {
            if ($entry['size'] > self::MAX_FILE_BYTES) {
                $skipped[] = [
                    'path' => $entry['sourcePath'],
                    'reason' => 'File exceeds maximum size of '.self::MAX_FILE_BYTES.' bytes.',
                ];

                continue;
            }

            if (count($files) >= self::MAX_FILES) {
                $skipped[] = [
                    'path' => $entry['sourcePath'],
                    'reason' => 'Snapshot exceeds maximum limit of '.self::MAX_FILES.' files.',
                ];

                continue;
            }

            $body = $this->fetchBlobBody($connection, $entry['sha']);

            if ($body === null) {
                $skipped[] = [
                    'path' => $entry['sourcePath'],
                    'reason' => 'File content could not be decoded as valid UTF-8.',
                ];

                continue;
            }

            if (strlen($body) > self::MAX_FILE_BYTES) {
                $skipped[] = [
                    'path' => $entry['sourcePath'],
                    'reason' => 'File exceeds maximum size of '.self::MAX_FILE_BYTES.' bytes.',
                ];

                continue;
            }

            $files[$entry['sourcePath']] = $body;
        }

        return new RepositoryDocsSnapshot($commitSha, $files, $skipped);
    }

    /**
     * @param  array<int, mixed>  $rawEntries
     * @return list<array{sourcePath: string, sha: string, size: int}>
     */
    private function filterEligibleEntries(array $rawEntries, string $docsPath): array
    {
        $normalizedDocsPath = trim($docsPath, '/');
        $prefix = $normalizedDocsPath.'/';
        $prefixLength = strlen($prefix);
        $eligible = [];

        foreach ($rawEntries as $entry) {
            if (! is_array($entry) || ($entry['type'] ?? null) !== self::BLOB_TYPE) {
                continue;
            }

            $path = $entry['path'] ?? null;
            $sha = $entry['sha'] ?? null;

            if (! is_string($path) || ! is_string($sha) || $sha === '' || ! str_starts_with($path, $prefix)) {
                continue;
            }

            $relativePath = substr($path, $prefixLength);

            if (
                $relativePath === ''
                || ! str_ends_with($relativePath, self::MARKDOWN_EXTENSION)
                || str_starts_with($relativePath, self::ARCHIVE_PREFIX)
            ) {
                continue;
            }

            $eligible[] = [
                'sourcePath' => $normalizedDocsPath.'/'.$relativePath,
                'sha' => $sha,
                'size' => is_int($entry['size'] ?? null) ? $entry['size'] : 0,
            ];
        }

        usort($eligible, fn (array $left, array $right): int => strnatcasecmp($left['sourcePath'], $right['sourcePath']));

        return $eligible;
    }

    private function fetchBlobBody(RepositoryConnection $connection, string $blobSha): ?string
    {
        $response = $this->get(
            $connection,
            "/repos/{$this->encodedRepository($connection)}/git/blobs/".rawurlencode($blobSha),
        );

        return $this->decodeBlobContent($response);
    }

    private function decodeBlobContent(Response $response): ?string
    {
        $encoding = (string) $response->json('encoding', 'base64');
        $content = $response->json('content');

        if (! is_string($content)) {
            return null;
        }

        if ($encoding === 'base64') {
            $cleaned = preg_replace('/\s+/', '', $content);
            $decoded = is_string($cleaned) ? base64_decode($cleaned, true) : false;

            if ($decoded === false) {
                return null;
            }

            $content = $decoded;
        } elseif ($encoding !== 'utf-8') {
            return null;
        }

        return mb_check_encoding($content, 'UTF-8') ? $content : null;
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private function get(RepositoryConnection $connection, string $path, array $query = []): Response
    {
        $response = $this->client($connection)->get($path, $query);
        $this->ensureSuccessfulResponse($connection, $response);

        return $response;
    }

    private function ensureSuccessfulResponse(RepositoryConnection $connection, Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $rateLimitRemaining = trim($response->header('x-ratelimit-remaining'));

        if (in_array($status, [403, 429], true) && $rateLimitRemaining === '0') {
            throw RepositoryRateLimitedException::forConnection($connection, $status);
        }

        if (in_array($status, [401, 403, 404], true)) {
            throw RepositoryAccessDeniedException::forConnection($connection, $status);
        }

        $response->throw();
    }

    private function client(RepositoryConnection $connection): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.github.base_url', self::DEFAULT_BASE_URL), '/');
        $apiVersion = (string) config('services.github.api_version', self::API_VERSION);
        $timeout = (int) config('services.github.timeout', self::DEFAULT_TIMEOUT_SECONDS);

        return Http::baseUrl($baseUrl)
            ->withToken((string) $connection->token)
            ->accept(self::ACCEPT_HEADER)
            ->withHeaders([
                'X-GitHub-Api-Version' => $apiVersion,
            ])
            ->timeout($timeout);
    }

    private function encodedRepository(RepositoryConnection $connection): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $connection->repository, 2)));
    }

    private function encodedBranch(RepositoryConnection $connection): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $connection->branch)));
    }
}
