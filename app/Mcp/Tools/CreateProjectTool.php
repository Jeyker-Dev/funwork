<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\CreateProjectAction;
use App\Data\Mcp\CreateProjectData;
use App\Data\Projects\ProjectData;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Mcp\Support\McpPresenter;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_project')]
#[Description('Create a new FunWork project with its default areas for the authenticated user.')]
class CreateProjectTool extends FunWorkTool
{
    protected string $authorizationMessage = 'You are not authorized to create projects.';

    public function __construct(
        private readonly CreateProjectAction $createProject,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->required()
                ->max(255)
                ->description('Project name (max 255 characters).'),
            'description' => $schema->string()
                ->description('Optional project description.'),
            'type' => $schema->string()
                ->enum(array_column(ProjectType::cases(), 'value'))
                ->default(ProjectData::DEFAULT_TYPE)
                ->description('Optional project type: programming, study, music, design, product, docs, or personal.'),
            'status' => $schema->string()
                ->enum(array_column(ProjectStatus::cases(), 'value'))
                ->default(ProjectData::DEFAULT_STATUS)
                ->description('Optional initial project status: active, planning, on-hold, done, or completed.'),
        ];
    }

    /**
     * @throws \JsonException
     */
    protected function execute(Request $request, User $user): Response
    {
        $input = CreateProjectData::fromRequest($request);

        $project = ($this->createProject)($user, $input->project);

        return Response::json(McpPresenter::project($project));
    }
}
