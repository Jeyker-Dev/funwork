<?php

namespace App\Jobs;

use App\Actions\Notebooks\SyncNotebookToGoogleDocAction;
use App\Models\Notebook;
use App\Services\Google\GoogleAuthorizationException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

class SyncNotebookToGoogleDocJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $notebookId;

    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(Notebook|int $notebook)
    {
        $this->notebookId = $notebook instanceof Notebook ? $notebook->id : $notebook;
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->notebookId;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Execute the job.
     */
    public function handle(SyncNotebookToGoogleDocAction $action): void
    {
        $notebook = Notebook::with('project.owner')->find($this->notebookId);

        if (! $notebook) {
            return;
        }

        if (! $notebook->google_doc_id) {
            return;
        }

        if ($notebook->google_doc_synced_at !== null && ($notebook->content_changed_at === null || $notebook->google_doc_synced_at->gte($notebook->content_changed_at))) {
            return;
        }

        $owner = $notebook->project?->owner;

        if (! $owner) {
            return;
        }

        try {
            $action($notebook, $owner);
        } catch (GoogleAuthorizationException) {
            // Do not retry: the action already deletes the Google account.
            $this->fail();

            return;
        } catch (RequestException|ConnectionException $e) {
            if ($this->attempts() >= $this->tries) {
                $this->fail($e);

                return;
            }

            throw $e;
        } catch (Throwable $e) {
            report($e);
            $this->fail($e);

            return;
        }
    }
}
