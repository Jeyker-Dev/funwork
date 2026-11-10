<?php

namespace App\Mcp\Tools;

use App\Actions\Notebooks\BuildNotebookExportAction;
use App\Data\Mcp\ReadNotebookData;
use App\Mcp\Support\McpPresenter;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('read_notebook')]
#[Description('Read a FunWork notebook exported as Markdown (by notebook_id, or by project_id and name).')]
class ReadNotebookTool extends Tool
{
    public function __construct(
        private BuildNotebookExportAction $buildExport,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'notebook_id' => $schema->integer()
                ->description('ID of the notebook to read.'),
            'project_id' => $schema->integer()
                ->description('ID of the project when looking up a notebook by name.'),
            'name' => $schema->string()
                ->description('Name of the notebook within the project (e.g. "Docs").'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        $input = ReadNotebookData::fromRequest($request);

        if ($input->notebookId !== null) {
            $notebook = Notebook::with(['project', 'area', 'pages'])->find($input->notebookId);
        } else {
            $notebook = Notebook::with(['project', 'area', 'pages'])
                ->where('project_id', $input->projectId)
                ->where('name', $input->name)
                ->first();
        }

        if (! $notebook instanceof Notebook) {
            return Response::error('Notebook not found.');
        }

        try {
            Gate::forUser($user)->authorize('view', $notebook);
        } catch (AuthorizationException) {
            return Response::error('You are not authorized to view this notebook.');
        }

        return Response::json([
            'id' => $notebook->id,
            'project_id' => $notebook->project_id,
            'name' => $notebook->name,
            'description' => $notebook->description,
            'area' => McpPresenter::area($notebook->area),
            'markdown' => $this->buildExport->execute($notebook),
        ]);
    }
}
