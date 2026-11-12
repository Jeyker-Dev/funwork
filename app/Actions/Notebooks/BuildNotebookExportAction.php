<?php

namespace App\Actions\Notebooks;

use App\Models\Notebook;

/**
 * Builds a single Markdown document with a notebook's pages,
 * meant to be downloaded or synchronized with Google Docs.
 *
 * It does not authorize: callers check access first.
 */
class BuildNotebookExportAction
{
    public function __invoke(Notebook $notebook): string
    {
        $sections = [
            "# {$notebook->name}",
            'Generado por FunWork el '.now()->utc()->format('Y-m-d H:i').' UTC.',
        ];

        if (filled($notebook->description)) {
            $sections[] = $notebook->description;
        }

        $pages = $notebook->relationLoaded('pages')
            ? $notebook->pages->sortBy([['order', 'asc'], ['id', 'asc']])
            : $notebook->pages()->orderBy('order')->orderBy('id')->get();

        if ($pages->isEmpty()) {
            $sections[] = '_Sin páginas._';
        } else {
            foreach ($pages->values() as $index => $page) {
                $content = is_array($page->content) ? $page->content : [];
                $title = filled($content['title'] ?? null) ? $content['title'] : 'Página '.($index + 1);

                $sections[] = "## {$title}";

                if (filled($content['body'] ?? null)) {
                    $sections[] = trim($content['body']);
                }
            }
        }

        return implode("\n\n", $sections)."\n";
    }
}
