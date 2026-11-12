<?php

namespace App\Actions\Notebooks;

use App\Exceptions\Notebooks\NotebookPageCannotBeEmptyException;
use App\Models\NotebookPage;

/**
 * Saves title and body for a notebook page.
 *
 * Enforces that a page cannot be empty unless it is the only page in the notebook.
 * Callers check authorization before executing.
 */
class SaveNotebookPageAction
{
    public function execute(NotebookPage $page, string $title, string $body): NotebookPage
    {
        if (trim($title) === '' && trim($body) === '') {
            $notebook = $page->notebook;

            if ($notebook && $notebook->pages()->count() > 1) {
                throw new NotebookPageCannotBeEmptyException;
            }
        }

        $page->update([
            'content' => [
                'title' => $title,
                'body' => $body,
            ],
        ]);

        return $page;
    }

    public function __invoke(NotebookPage $page, string $title, string $body): NotebookPage
    {
        return $this->execute($page, $title, $body);
    }
}
