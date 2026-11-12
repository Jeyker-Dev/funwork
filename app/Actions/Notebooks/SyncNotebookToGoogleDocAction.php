<?php

namespace App\Actions\Notebooks;

use App\Models\GoogleAccount;
use App\Models\Notebook;
use App\Models\User;
use App\Services\Google\GoogleAuthorizationException;
use App\Services\Google\GoogleDriveClient;
use Illuminate\Support\Facades\Gate;

/**
 * Publishes a notebook to a Google Doc in the user's Drive.
 *
 * The first sync creates the Doc; later syncs replace its content and keep
 * the same file ID, so NotebookLM only needs "Sync" to refresh the source.
 */
class SyncNotebookToGoogleDocAction
{
    public function __construct(
        private BuildNotebookExportAction $buildExport,
        private GoogleDriveClient $drive,
    ) {}

    /**
     * Returns the Google Doc file ID.
     *
     * @throws GoogleAuthorizationException when the user has to connect Google again
     */
    public function __invoke(Notebook $notebook, User $user): string
    {
        Gate::forUser($user)->authorize('update', $notebook);

        $account = GoogleAccount::where('user_id', $user->id)->first();

        if (! $account) {
            throw new GoogleAuthorizationException('The user has not connected a Google account.');
        }

        $markdown = ($this->buildExport)($notebook);

        try {
            $docId = $notebook->google_doc_id;

            if (! $docId || ! $this->drive->replaceDocument($account, $docId, $markdown)) {
                $docName = "{$notebook->name} — {$notebook->project->name} (FunWork)";
                $docId = $this->drive->createDocument($account, $docName, $markdown);
            }
        } catch (GoogleAuthorizationException $e) {
            // The stored tokens are useless now; drop them so the user reconnects.
            $account->delete();

            throw $e;
        }

        $notebook->forceFill([
            'google_doc_id' => $docId,
            'google_doc_synced_at' => now(),
        ])->save();

        return $docId;
    }
}
