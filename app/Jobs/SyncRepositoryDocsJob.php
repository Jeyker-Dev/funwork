<?php

namespace App\Jobs;

use App\Actions\Notebooks\SyncDocsNotebookAction;
use App\Contracts\RepositoryDocsSource;
use App\Models\RepositoryConnection;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Syncs a repository connection's branch head docs into its project notebook.
 */
class SyncRepositoryDocsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public RepositoryConnection $repositoryConnection) {}

    /**
     * Unique per connection so queued pushes collapse into one sync of the current head.
     */
    public function uniqueId(): string
    {
        return (string) $this->repositoryConnection->id;
    }

    public function handle(RepositoryDocsSource $docsSource, SyncDocsNotebookAction $syncDocsNotebook): void
    {
        $connection = $this->repositoryConnection->fresh(['project', 'area']);

        if (! $connection instanceof RepositoryConnection) {
            return;
        }

        $headSha = $docsSource->headCommit($connection);

        if ($connection->last_synced_sha === $headSha) {
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
    }
}
