<?php

namespace App\Jobs;

use App\Actions\Notebooks\SyncDocsNotebookAction;
use App\Contracts\RepositoryDocsSource;
use App\Exceptions\RepositoryConnections\RepositoryAccessDeniedException;
use App\Exceptions\RepositoryConnections\RepositoryRateLimitedException;
use App\Exceptions\RepositoryConnections\RepositoryTreeTruncatedException;
use App\Models\RepositoryConnection;
use App\Notifications\RepositoryConnectionBrokenNotification;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Syncs a repository connection's branch head docs into its project notebook.
 */
class SyncRepositoryDocsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public const int OVERLAP_RELEASE_SECONDS = 15;

    public const int OVERLAP_EXPIRE_SECONDS = 300;

    public const int RATE_LIMIT_RELEASE_SECONDS = 300;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public RepositoryConnection $repositoryConnection) {}

    /**
     * Unique per connection so queued pushes collapse into one sync of the current head.
     */
    public function uniqueId(): string
    {
        return (string) $this->repositoryConnection->id;
    }

    /**
     * Prevent two workers from syncing the same repository connection concurrently.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->repositoryConnection->id))
                ->releaseAfter(self::OVERLAP_RELEASE_SECONDS)
                ->expireAfter(self::OVERLAP_EXPIRE_SECONDS),
        ];
    }

    public function handle(RepositoryDocsSource $docsSource, SyncDocsNotebookAction $syncDocsNotebook): void
    {
        $connection = $this->repositoryConnection->fresh(['project.owner', 'area']);

        if (! $connection instanceof RepositoryConnection) {
            return;
        }

        try {
            $headSha = $docsSource->headCommit($connection);

            if ($connection->isActive() && $connection->last_error === null && $connection->last_synced_sha === $headSha) {
                return;
            }

            $snapshot = $docsSource->fetchDocs($connection, $headSha);

            DB::transaction(function () use ($connection, $snapshot, $syncDocsNotebook): void {
                ($syncDocsNotebook)(
                    $connection->project,
                    $snapshot->files,
                    $connection->area,
                    array_column($snapshot->skipped, 'path'),
                );

                $connection->markSynced($snapshot->commitSha);
            });
        } catch (RepositoryAccessDeniedException $exception) {
            $this->handleAccessDenied($connection, $exception);
            $this->fail($exception);

            return;
        } catch (RepositoryRateLimitedException) {
            $this->release(self::RATE_LIMIT_RELEASE_SECONDS);

            return;
        } catch (RepositoryTreeTruncatedException $exception) {
            $connection->recordError($this->safeErrorMessage($exception, $connection));
            $this->fail($exception);

            return;
        }
    }

    /**
     * Record a safe error summary after retries are exhausted on unexpected failures.
     */
    public function failed(Throwable $exception): void
    {
        if ($exception instanceof RepositoryAccessDeniedException || $exception instanceof RepositoryTreeTruncatedException) {
            return;
        }

        $connection = $this->repositoryConnection->fresh();

        if (! $connection instanceof RepositoryConnection) {
            return;
        }

        $connection->recordError($this->safeErrorMessage($exception, $connection));
    }

    private function handleAccessDenied(RepositoryConnection $connection, RepositoryAccessDeniedException $exception): void
    {
        $error = $this->safeErrorMessage($exception, $connection);

        $transitionedToBroken = DB::transaction(function () use ($connection, $error): bool {
            $locked = RepositoryConnection::query()->lockForUpdate()->find($connection->id);

            if (! $locked instanceof RepositoryConnection) {
                return false;
            }

            $wasActive = $locked->isActive();
            $locked->markBroken($error);

            return $wasActive;
        });

        if ($transitionedToBroken) {
            $connection->project?->owner?->notify(new RepositoryConnectionBrokenNotification($connection, $error));
        }
    }

    private function safeErrorMessage(Throwable $exception, RepositoryConnection $connection): string
    {
        $message = match (true) {
            $exception instanceof RepositoryAccessDeniedException,
            $exception instanceof RepositoryTreeTruncatedException => $exception->getMessage(),
            $exception instanceof RequestException => "GitHub request failed (HTTP {$exception->response->status()}).",
            $exception instanceof ConnectionException => 'Could not connect to GitHub.',
            default => 'Docs sync failed unexpectedly.',
        };

        $token = (string) $connection->token;

        if ($token !== '') {
            $message = str_replace($token, '[REDACTED]', $message);
        }

        return $message;
    }
}
