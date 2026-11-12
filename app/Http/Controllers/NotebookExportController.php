<?php

namespace App\Http\Controllers;

use App\Actions\Notebooks\BuildNotebookExportAction;
use App\Models\Notebook;
use App\Models\Project;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotebookExportController extends Controller
{
    public function __invoke(Project $project, Notebook $notebook, BuildNotebookExportAction $action): StreamedResponse
    {
        $markdown = $action($notebook);

        $filename = (Str::slug($notebook->name) ?: 'notebook').'.md';

        return response()->streamDownload(
            function () use ($markdown) {
                echo $markdown;
            },
            $filename,
            ['Content-Type' => 'text/markdown; charset=UTF-8'],
        );
    }
}
