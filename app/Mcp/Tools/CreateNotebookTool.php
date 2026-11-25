<?php

namespace App\Mcp\Tools;

use App\Actions\Notebooks\CreateNotebookAction;
use App\Data\Mcp\CreateNotebookData;
use App\Mcp\Support\McpPresenter;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_notebook')]
#[Description('Create a new notebook in a FunWork project, optionally assigned to an area.')]
class CreateNotebookTool extends FunWorkTool
{
    protected string $authorizationMessage = 'You are not authorized to create notebooks in this project.';

    public function __construct(
        private readonly CreateNotebookAction $createNotebook,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID of the target project.'),
            'name' => $schema->string()
                ->required()
                ->max(255)
                ->description('Notebook name (max 255 characters).'),
            'description' => $schema->string()
                ->description('Optional notebook description.'),
            'area' => $schema->string()
                ->description('Optional project area slug or key (e.g. backend, frontend, database, design).'),
        ];
    }

    /**
     * @throws \JsonException
     */
    protected function execute(Request $request, User $user): Response
    {
        $input = CreateNotebookData::fromRequest($request);

        $project = Project::findOrFail($input->projectId);

        Gate::forUser($user)->authorize('create', [Notebook::class, $project]);

        $area = $input->areaKey !== null
            ? $project->areaByKey($input->areaKey)
            : null;

        $notebook = ($this->createNotebook)($project, $user, $input->notebook, $area);

        return Response::json(McpPresenter::notebook($notebook));
    }
}
