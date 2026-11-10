<?php

namespace App\Mcp\Tools;

use App\Actions\Tasks\CreateTaskAction;
use App\Data\Mcp\AddTaskData;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Support\McpPresenter;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add_task')]
#[Description('Create a new task in a FunWork project, assigning it to the authenticated user.')]
class AddTaskTool extends Tool
{
    public function __construct(
        private CreateTaskAction $createTask,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID of the target project.'),
            'title' => $schema->string()
                ->required()
                ->description('Task title (max 255 characters).'),
            'description' => $schema->string()
                ->required()
                ->description('Task acceptance criteria and details.'),
            'area' => $schema->string()
                ->description('Optional project area slug or key (e.g. backend, frontend, database, design).'),
            'status' => $schema->string()
                ->enum(array_column(TaskStatus::cases(), 'value'))
                ->default(TaskStatus::Todo->value)
                ->description('Initial task status: Todo, Doing, or Done.'),
            'priority' => $schema->string()
                ->enum(array_column(TaskPriority::cases(), 'value'))
                ->default(TaskPriority::Low->value)
                ->description('Task priority: Low, Medium, or High.'),
            'due_date' => $schema->string()
                ->description('Optional due date in Y-m-d format.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        $input = AddTaskData::fromRequest($request);

        $project = Project::find($input->projectId);

        if (! $project instanceof Project) {
            return Response::error('Project not found.');
        }

        $area = null;

        if ($input->areaKey !== null) {
            $area = $project->areaByKey($input->areaKey);

            if ($area === null) {
                return Response::error("Area [{$input->areaKey}] not found in this project.");
            }
        }

        try {
            $task = ($this->createTask)($project, $user, $input, $area);
        } catch (AuthorizationException) {
            return Response::error('You are not authorized to create tasks in this project.');
        }

        return Response::json(McpPresenter::taskDetail($task));
    }
}
