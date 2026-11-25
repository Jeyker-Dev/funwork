<?php

namespace App\Mcp\Tools;

use App\Actions\Tasks\CreateTaskAction;
use App\Data\Mcp\AddTaskData;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Support\McpPresenter;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add_task')]
#[Description('Create a new task in a FunWork project, assigning it to the authenticated user.')]
class AddTaskTool extends FunWorkTool
{
    protected string $authorizationMessage = 'You are not authorized to create tasks in this project.';

    public function __construct(
        private readonly CreateTaskAction $createTask,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID of the target project.'),
            ...self::itemSchema($schema),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public static function itemSchema(JsonSchema $schema): array
    {
        return [
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

    /**
     * @throws \JsonException
     */
    protected function execute(Request $request, User $user): Response
    {
        $input = AddTaskData::fromRequest($request);

        $project = Project::findOrFail($input->projectId);

        Gate::forUser($user)->authorize('create', [Task::class, $project]);

        $area = $input->areaKey !== null
            ? $project->areaByKey($input->areaKey)
            : null;

        $task = ($this->createTask)($project, $user, $input, $area);

        return Response::json(McpPresenter::taskDetail($task));
    }
}
