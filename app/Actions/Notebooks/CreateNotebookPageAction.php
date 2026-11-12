<?php

namespace App\Actions\Notebooks;

use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\User;

/**
 * Creates the next page of a notebook with incremented order.
 *
 * Callers check authorization before executing.
 */
class CreateNotebookPageAction
{
    /**
     * @param  array{title?: string, body?: string}  $content
     */
    public function execute(Notebook $notebook, User $user, array $content = ['title' => '', 'body' => '']): NotebookPage
    {
        $maxOrder = $notebook->pages()->max('order');
        $nextOrder = ($maxOrder !== null ? (int) $maxOrder : 0) + 1;

        return $notebook->pages()->create([
            'content' => $content,
            'order' => $nextOrder,
            'created_by' => $user->id,
        ]);
    }

    /**
     * @param  array{title?: string, body?: string}  $content
     */
    public function __invoke(Notebook $notebook, User $user, array $content = ['title' => '', 'body' => '']): NotebookPage
    {
        return $this->execute($notebook, $user, $content);
    }
}
