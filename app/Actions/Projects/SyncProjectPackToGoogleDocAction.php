<?php

namespace App\Actions\Projects;

use App\Models\GoogleAccount;
use App\Models\Project;
use App\Models\User;
use App\Services\Google\GoogleAuthorizationException;
use App\Services\Google\GoogleDriveClient;
use Illuminate\Support\Facades\Gate;

/**
 * Publishes the project pack to a Google Doc in the user's Drive.
 *
 * The first sync creates the Doc; later syncs replace its content and keep
 * the same file ID, so NotebookLM only needs "Sync" to refresh the source.
 */
class SyncProjectPackToGoogleDocAction
{
    public function __construct(
        private BuildProjectPackAction $buildPack,
        private GoogleDriveClient $drive,
    ) {}

    /**
     * Returns the Google Doc file ID.
     *
     * @throws GoogleAuthorizationException when the user has to connect Google again
     */
    public function execute(Project $project, User $user): string
    {
        Gate::forUser($user)->authorize('update', $project);

        $account = GoogleAccount::where('user_id', $user->id)->first();

        if (! $account) {
            throw new GoogleAuthorizationException('The user has not connected a Google account.');
        }

        $markdown = $this->buildPack->execute($project);

        try {
            $docId = $project->google_doc_id;

            if (! $docId || ! $this->drive->replaceDocument($account, $docId, $markdown)) {
                $docId = $this->drive->createDocument($account, "{$project->name} — Project pack (FunWork)", $markdown);
            }
        } catch (GoogleAuthorizationException $e) {
            // The stored tokens are useless now; drop them so the user reconnects.
            $account->delete();

            throw $e;
        }

        $project->forceFill([
            'google_doc_id' => $docId,
            'google_doc_synced_at' => now(),
        ])->save();

        return $docId;
    }
}
