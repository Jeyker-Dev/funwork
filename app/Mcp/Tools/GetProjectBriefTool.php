<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\BuildProjectContextAction;
use App\Data\Mcp\GetProjectBriefData;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_project_brief')]
#[IsReadOnly]
#[Description('Get a concise Markdown brief of a FunWork project (overview, areas, open tasks, decisions, notebooks, and pending inbox items).')]
class GetProjectBriefTool extends FunWorkTool
{
    protected string $authorizationMessage = 'You are not authorized to access this project.';

    public function __construct(
        private readonly BuildProjectContextAction $buildContext,
    ) {}

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID of the project.'),
        ];
    }

    protected function execute(Request $request, User $user): Response
    {
        $input = GetProjectBriefData::fromRequest($request);

        $project = Project::findOrFail($input->projectId);

        Gate::forUser($user)->authorize('view', $project);

        return Response::text(($this->buildContext)($project, $user));
    }
}
