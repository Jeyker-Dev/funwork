<?php

namespace App\Actions\Notebooks;

use App\Models\NotebookPage;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a notebook page and returns the fallback page to navigate to, if any.
 *
 * Callers check authorization before executing.
 */
class DeleteNotebookPageAction
{
    public function __invoke(NotebookPage $page): ?NotebookPage
    {
        return DB::transaction(function () use ($page): ?NotebookPage {
            $fallbackPage = $page->fallbackPageAfterDelete();

            $page->delete();

            return $fallbackPage;
        });
    }
}
