<?php

namespace App\Http\Controllers;

use App\Actions\RepositoryConnections\RequestRepositoryDocsSyncAction;
use App\Models\RepositoryConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles signed GitHub repository webhook deliveries for docs synchronization.
 */
class GitHubRepositoryWebhookController extends Controller
{
    private const int GITHUB_COMMIT_LIST_LIMIT = 20;

    private const string SIGNATURE_PREFIX = 'sha256=';

    public function __invoke(Request $request, RepositoryConnection $connection, RequestRepositoryDocsSyncAction $requestSync): JsonResponse
    {
        if (! $this->hasValidSignature($request, $connection)) {
            return response()->json(['status' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $event = $request->header('X-GitHub-Event');

        if ($event === 'ping') {
            return response()->json(['status' => 'ok'], Response::HTTP_OK);
        }

        if ($event !== 'push') {
            return response()->json(['status' => 'ignored'], Response::HTTP_ACCEPTED);
        }

        if ($connection->isBroken()) {
            return response()->json(['status' => 'ignored'], Response::HTTP_ACCEPTED);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! $this->shouldDispatchForPush($payload, $connection)) {
            return response()->json(['status' => 'ignored'], Response::HTTP_ACCEPTED);
        }

        $requestSync($connection);

        return response()->json(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }

    private function hasValidSignature(Request $request, RepositoryConnection $connection): bool
    {
        $signature = $request->header('X-Hub-Signature-256');
        $secret = $connection->webhook_secret;

        if (! is_string($signature) || $signature === '' || ! is_string($secret) || $secret === '') {
            return false;
        }

        $expected = self::SIGNATURE_PREFIX.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shouldDispatchForPush(array $payload, RepositoryConnection $connection): bool
    {
        if (($payload['ref'] ?? null) !== 'refs/heads/'.$connection->branch) {
            return false;
        }

        if (($payload['deleted'] ?? false) === true) {
            return false;
        }

        $commits = $payload['commits'] ?? null;

        // GitHub omits or truncates commit file lists on empty pushes and >20-commit pushes, so sync defensively.
        if (! is_array($commits) || $commits === [] || count($commits) >= self::GITHUB_COMMIT_LIST_LIMIT) {
            return true;
        }

        $docsPrefix = trim($connection->docs_path, '/').'/';

        foreach ($commits as $commit) {
            if (! is_array($commit) || ! $this->hasCommitFileLists($commit)) {
                return true;
            }

            if ($this->commitTouchesDocs($commit, $docsPrefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $commit
     */
    private function hasCommitFileLists(array $commit): bool
    {
        return is_array($commit['added'] ?? null)
            || is_array($commit['modified'] ?? null)
            || is_array($commit['removed'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $commit
     */
    private function commitTouchesDocs(array $commit, string $docsPrefix): bool
    {
        foreach (['added', 'modified', 'removed'] as $key) {
            $paths = $commit[$key] ?? [];

            if (! is_array($paths)) {
                continue;
            }

            foreach ($paths as $path) {
                if (is_string($path) && str_starts_with(ltrim($path, '/'), $docsPrefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
