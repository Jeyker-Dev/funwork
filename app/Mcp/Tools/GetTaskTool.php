<?php

namespace App\Mcp\Tools;

use App\Data\Mcp\GetTaskData;
use App\Mcp\Support\McpPresenter;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_task')]
#[Description('Get the full details of a single FunWork task by ID.')]
class GetTaskTool extends FunWorkTool
{
    protected string $authorizationMessage = 'You are not authorized to view this task.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()
                ->required()
                ->description('ID of the task.'),
        ];
    }

    protected function execute(Request $request, User $user): Response
    {
        $input = GetTaskData::fromRequest($request);

        $task = Task::with(['project', 'area'])->findOrFail($input->taskId);

        Gate::forUser($user)->authorize('view', $task);

        return Response::json(McpPresenter::taskDetail($task));
    }
}
