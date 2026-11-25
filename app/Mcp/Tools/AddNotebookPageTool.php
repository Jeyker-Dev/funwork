<?php

namespace App\Mcp\Tools;

use App\Actions\Notebooks\CreateNotebookPageAction;
use App\Data\Mcp\AddNotebookPageData;
use App\Mcp\Support\McpPresenter;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add_notebook_page')]
#[Description('Add a new Markdown page (title and body) to an existing FunWork notebook.')]
class AddNotebookPageTool extends FunWorkTool
{
    protected string $authorizationMessage = 'You are not authorized to add pages to this notebook.';

    public function __construct(
        private readonly CreateNotebookPageAction $createPage,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'notebook_id' => $schema->integer()
                ->required()
                ->description('ID of the target notebook.'),
            'title' => $schema->string()
                ->required()
                ->max(255)
                ->description('Page title (max 255 characters).'),
            'body' => $schema->string()
                ->required()
                ->max(AddNotebookPageData::MAX_BODY_LENGTH)
                ->description('Page body in Markdown (max 65535 characters).'),
        ];
    }

    /**
     * @throws \JsonException
     */
    protected function execute(Request $request, User $user): Response
    {
        $input = AddNotebookPageData::fromRequest($request);

        $notebook = Notebook::with('project')->findOrFail($input->notebookId);

        Gate::forUser($user)->authorize('create', [NotebookPage::class, $notebook]);

        $page = ($this->createPage)($notebook, $user, [
            'title' => $input->title,
            'body' => $input->body,
        ]);

        return Response::json(McpPresenter::notebookPage($page));
    }
}
