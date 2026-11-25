<?php

namespace App\Mcp\Tools;

use App\Actions\Tasks\CreateTaskAction;
use App\Data\Mcp\AddTasksData;
use App\Mcp\Support\McpPresenter;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add_tasks')]
#[Description('Create multiple tasks in a FunWork project in a single all-or-nothing transaction, assigning them to the authenticated user.')]
class AddTasksTool extends FunWorkTool
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
            'tasks' => $schema->array()
                ->required()
                ->min(1)
                ->max(AddTasksData::MAX_TASKS_PER_CALL)
                ->items($schema->object(AddTaskTool::itemSchema($schema)))
                ->description('Non-empty list of tasks to create (max '.AddTasksData::MAX_TASKS_PER_CALL.' per call).'),
        ];
    }

    /**
     * @throws \JsonException
     */
    protected function execute(Request $request, User $user): Response
    {
        $input = AddTasksData::fromRequest($request);

        $project = Project::findOrFail($input->projectId);

        Gate::forUser($user)->authorize('create', [Task::class, $project]);

        $areasByIndex = [];

        foreach ($input->tasks as $index => $taskInput) {
            $areasByIndex[$index] = $taskInput->areaKey !== null
                ? $project->areaByKey($taskInput->areaKey)
                : null;
        }

        $createdTasks = DB::transaction(function () use ($project, $user, $input, $areasByIndex): array {
            $tasks = [];

            foreach ($input->tasks as $index => $taskInput) {
                $tasks[] = ($this->createTask)($project, $user, $taskInput, $areasByIndex[$index]);
            }

            return $tasks;
        });

        return Response::json([
            'tasks' => array_map(
                fn (Task $task): array => McpPresenter::taskDetail($task),
                $createdTasks,
            ),
        ]);
    }
}
