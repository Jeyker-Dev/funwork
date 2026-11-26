<?php

namespace App\Actions\RepositoryConnections;

use App\Jobs\SyncRepositoryDocsJob;
use App\Models\RepositoryConnection;

/**
 * Marks a connection as syncing and queues its docs sync.
 *
 * Every sync trigger (create, update, sync now, webhook) goes through here so the
 * settings page can show progress until the job finishes. Callers authorize first.
 */
class RequestRepositoryDocsSyncAction
{
    public function __invoke(RepositoryConnection $connection): void
    {
        $connection->markSyncRequested();

        SyncRepositoryDocsJob::dispatch($connection);
    }
}
